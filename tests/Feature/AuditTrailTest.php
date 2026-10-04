<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Support\LogSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Admin', 'role_id' => 1]);
    Sanctum::actingAs($this->admin);
    $this->category = Category::create(['name' => 'Keramik']);
    TransactionType::forceCreate(['name' => 'in']);
    TransactionType::forceCreate(['name' => 'out']);
});

function auditProductPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'name' => 'Granit Putih',
        'price_1' => 100000,
        'price_2' => 110000,
        'size' => '60x60',
        'stock' => 10,
        'surface' => 'Glossy',
        'type' => 'GP1',
    ], $overrides);
}

function auditLogsOf(string $type)
{
    return AuditLog::where('auditable_type', $type)->orderBy('id')->get();
}

test('creating, updating and deleting a product is audited with before and after values', function () {
    $id = $this->postJson('/api/products', auditProductPayload())->assertCreated()->json('id');

    $this->putJson("/api/products/$id", auditProductPayload(['price_1' => 125000, 'stock' => 7]))
        ->assertOk();
    $this->deleteJson("/api/products/$id")->assertOk();

    $logs = auditLogsOf('product');
    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('event')->all())->toBe(['created', 'updated', 'deleted']);

    [$created, $updated, $deleted] = $logs;
    expect($created->old_values)->toBeNull()
        ->and($created->new_values)->toMatchArray(['name' => 'Granit Putih', 'price_1' => 100000])
        ->and($created->new_values)->not->toHaveKeys(['created_at', 'updated_at'])
        ->and($created->user_id)->toBe($this->admin->id)
        ->and($created->label)->toBe('Granit Putih');

    // Only the changed fields are stored.
    expect($updated->old_values)->toEqual(['price_1' => 100000, 'stock' => 10])
        ->and($updated->new_values)->toEqual(['price_1' => 125000, 'stock' => 7]);

    expect($deleted->new_values)->toBeNull()
        ->and($deleted->old_values)->toMatchArray(['price_1' => 125000, 'stock' => 7]);
});

test('an update without changes is not audited', function () {
    $id = $this->postJson('/api/products', auditProductPayload())->json('id');

    $this->putJson("/api/products/$id", auditProductPayload())->assertOk();

    expect(auditLogsOf('product'))->toHaveCount(1);
});

test('product images are stored as a short marker instead of base64', function () {
    $image = 'data:image/jpeg;base64,' . str_repeat('A', 4096);

    $this->postJson('/api/products', auditProductPayload(['image' => $image]))->assertCreated();

    $value = auditLogsOf('product')->first()->new_values['image'];
    expect($value)->toBe(LogSanitizer::describeImage($image))
        ->and($value)->toStartWith('[image 4 KB · ');
});

test('user passwords are masked in the audit trail', function () {
    $id = $this->postJson('/api/users', [
        'name' => 'Budi',
        'email' => 'budi@real.com',
        'password' => 'rahasia123',
        'role_id' => 2,
    ])->assertCreated()->json('id');

    $this->putJson("/api/users/$id", [
        'name' => 'Budi',
        'email' => 'budi@real.com',
        'password' => 'baru12345',
        'role_id' => 2,
    ])->assertOk();

    $logs = AuditLog::where('auditable_type', 'user')->where('auditable_id', $id)->orderBy('id')->get();
    expect($logs[0]->new_values['password'])->toBe(LogSanitizer::MASK)
        ->and($logs[1]->old_values)->toEqual(['password' => LogSanitizer::MASK])
        ->and($logs[1]->new_values)->toEqual(['password' => LogSanitizer::MASK])
        ->and(AuditLog::all()->toJson())->not->toContain('rahasia123')
        ->and(AuditLog::all()->toJson())->not->toContain('baru12345');
});

test('categories are audited', function () {
    $id = $this->postJson('/api/categories', ['name' => 'Granit'])->assertCreated()->json('id');
    $this->putJson("/api/categories/$id", ['name' => 'Granit Lantai'])->assertOk();

    $updated = auditLogsOf('category')->last();
    expect($updated->event)->toBe('updated')
        ->and($updated->old_values)->toEqual(['name' => 'Granit'])
        ->and($updated->new_values)->toEqual(['name' => 'Granit Lantai']);
});

test('a transaction audits itself and the stock change of its product', function () {
    $product = Product::create(auditProductPayload(['stock' => 10]));

    $transactionId = $this->postJson('/api/transactions', [
        'count' => 5,
        'product_id' => $product->id,
        'transaction_type_id' => 1,
    ])->assertCreated()->assertJsonMissingPath('product')->json('id');

    expect($product->fresh()->stock)->toBe(15);

    $transactionLog = auditLogsOf('transaction')->first();
    expect($transactionLog->event)->toBe('created')
        ->and($transactionLog->label)->toBe("#$transactionId · Granit Putih")
        ->and($transactionLog->new_values)->toMatchArray(['count' => 5, 'product_id' => $product->id]);

    $stockLog = auditLogsOf('product')->last();
    expect($stockLog->event)->toBe('updated')
        ->and($stockLog->old_values)->toEqual(['stock' => 10])
        ->and($stockLog->new_values)->toEqual(['stock' => 15]);

    $this->deleteJson("/api/transactions/$transactionId")->assertOk();

    expect($product->fresh()->stock)->toBe(10)
        ->and(Transaction::count())->toBe(0)
        ->and(auditLogsOf('transaction')->last()->event)->toBe('deleted')
        ->and(auditLogsOf('product')->last()->new_values)->toEqual(['stock' => 10]);
});

test('a transaction for an unknown product is rejected', function () {
    $this->postJson('/api/transactions', [
        'count' => 5,
        'product_id' => 999,
        'transaction_type_id' => 1,
    ])->assertStatus(400);

    expect(auditLogsOf('transaction'))->toHaveCount(0);
});

test('admins can list, summarise and read audit logs', function () {
    $id = $this->postJson('/api/products', auditProductPayload())->json('id');
    $this->putJson("/api/products/$id", auditProductPayload(['stock' => 3]))->assertOk();
    $this->postJson('/api/categories', ['name' => 'Granit'])->assertCreated();

    $this->getJson('/api/audit-logs/summary')
        ->assertOk()
        ->assertJson(['product' => 2, 'category' => 2, 'transaction' => 0]);

    $page = $this->getJson('/api/audit-logs?type=product&orderBy=created_at&order=desc&page=1')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0.event', 'updated')
        ->assertJsonPath('data.0.user_name', 'Admin');

    $this->getJson('/api/audit-logs/' . $page->json('data.0.id'))
        ->assertOk()
        ->assertJsonPath('old_values.stock', 10)
        ->assertJsonPath('new_values.stock', 3)
        ->assertJsonPath('user.name', 'Admin');

    $this->getJson('/api/audit-logs?type=product&quickFilter=nothing-matches')
        ->assertOk()
        ->assertJsonPath('total', 0);

    $this->getJson('/api/audit-logs?type=unknown')
        ->assertStatus(400)
        ->assertJsonPath('error', 'The selected type is invalid.');
});

test('audit and error logs are admin only', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 2]));

    $this->getJson('/api/audit-logs?type=product')->assertStatus(403)->assertJsonPath('error', 'Forbidden');
    $this->getJson('/api/audit-logs/summary')->assertStatus(403);
    $this->getJson('/api/error-logs')->assertStatus(403);
});
