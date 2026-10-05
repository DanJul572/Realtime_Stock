<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

beforeEach(function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));
    $this->category = Category::create(['name' => 'Keramik']);
});

function imageProductPayload(array $overrides = []): array
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

function pngDataUrl(): string
{
    return 'data:image/png;base64,' . PNG_1PX;
}

function storedImagePath(int $id): ?string
{
    return DB::table('products')->where('id', $id)->value('image');
}

test('an uploaded image is stored as a file and returned as a url', function () {
    $response = $this->postJson('/api/products', imageProductPayload(['image' => pngDataUrl()]))
        ->assertCreated();

    $path = storedImagePath($response->json('id'));
    expect($path)->toMatch('#^products/[0-9a-f-]{36}\.png$#')
        ->and(Storage::disk('public')->get($path))->toBe(base64_decode(PNG_1PX))
        ->and($response->json('image'))->toBe(Storage::disk('public')->url($path));

    $this->getJson('/api/products/' . $response->json('id'))
        ->assertOk()
        ->assertJsonPath('image', Storage::disk('public')->url($path));
});

test('products saved before keep their base64 image', function () {
    $product = Product::create(imageProductPayload());
    DB::table('products')->where('id', $product->id)->update(['image' => pngDataUrl()]);

    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('image', pngDataUrl());
    $this->getJson('/api/products?orderBy=products.id&order=asc&isWithoutImage=true')
        ->assertOk()
        ->assertJsonPath('total', 0);

    // Saving without a new image leaves it untouched.
    $this->putJson("/api/products/{$product->id}", imageProductPayload(['stock' => 3]))->assertOk();
    expect(storedImagePath($product->id))->toBe(pngDataUrl());
});

test('replacing an image deletes the old file', function () {
    $id = $this->postJson('/api/products', imageProductPayload(['image' => pngDataUrl()]))->json('id');
    $oldPath = storedImagePath($id);

    $this->putJson("/api/products/$id", imageProductPayload(['image' => pngDataUrl()]))->assertOk();

    $newPath = storedImagePath($id);
    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('an older base64 image can be replaced by a file', function () {
    $product = Product::create(imageProductPayload());
    DB::table('products')->where('id', $product->id)->update(['image' => pngDataUrl()]);

    $this->putJson("/api/products/{$product->id}", imageProductPayload(['image' => pngDataUrl()]))
        ->assertOk();

    Storage::disk('public')->assertExists(storedImagePath($product->id));
});

test('deleting a product deletes its image file', function () {
    $id = $this->postJson('/api/products', imageProductPayload(['image' => pngDataUrl()]))->json('id');
    $path = storedImagePath($id);

    $this->deleteJson("/api/products/$id")->assertOk();

    Storage::disk('public')->assertMissing($path);
});

test('files that are not images are rejected', function () {
    $this->postJson('/api/products', imageProductPayload([
        'image' => 'data:image/png;base64,' . base64_encode('<?php echo "hi";'),
    ]))->assertStatus(400)->assertJsonPath('error', fn ($error) => str_contains($error, 'JPEG, PNG'));

    expect(Product::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('a failed update keeps the old image and removes the new file', function () {
    $id = $this->postJson('/api/products', imageProductPayload(['image' => pngDataUrl()]))->json('id');
    $oldPath = storedImagePath($id);
    AuditLog::creating(fn () => throw new RuntimeException('Audit insert failed'));

    $this->putJson("/api/products/$id", imageProductPayload(['image' => pngDataUrl()]))->assertStatus(500);

    expect(storedImagePath($id))->toBe($oldPath)
        ->and(Storage::disk('public')->allFiles())->toBe([$oldPath]);
});

test('the move-images command moves base64 images to files', function () {
    $product = Product::create(imageProductPayload());
    $broken = Product::create(imageProductPayload(['name' => 'Rusak']));
    DB::table('products')->where('id', $product->id)->update(['image' => pngDataUrl()]);
    DB::table('products')->where('id', $broken->id)->update(['image' => 'data:image/png;base64,xx']);

    $this->artisan('products:move-images')
        ->expectsOutputToContain("Product #{$broken->id}")
        ->expectsOutputToContain('Moved 1 product image(s) to files, 1 failed.')
        ->assertSuccessful();

    $path = storedImagePath($product->id);
    expect($path)->toStartWith('products/')
        ->and(Storage::disk('public')->get($path))->toBe(base64_decode(PNG_1PX))
        ->and(storedImagePath($broken->id))->toBe('data:image/png;base64,xx');
});
