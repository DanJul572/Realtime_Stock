<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));
    $this->category = Category::create(['name' => 'Keramik']);
});

function productPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'name' => 'Produk A',
        'price_1' => 1000,
        'price_2' => 1100,
        'size' => '60x60',
        'stock' => 10,
        'surface' => 'Matte',
        'type' => 'T1',
    ], $overrides);
}

test('a product can be created with a code and without one', function () {
    $this->postJson('/api/products', productPayload(['code' => 'RS-ABC123']))
        ->assertCreated()
        ->assertJsonPath('code', 'RS-ABC123');

    $this->postJson('/api/products', productPayload(['name' => 'Produk B']))
        ->assertCreated();

    // An empty code is stored as null, so many products can have no code.
    $this->postJson('/api/products', productPayload(['name' => 'Produk C', 'code' => '']))
        ->assertCreated();

    expect(Product::whereNull('code')->count())->toBe(2);
});

test('codes must be unique on create', function () {
    $this->postJson('/api/products', productPayload(['code' => 'RS-DUP']))->assertCreated();

    $this->postJson('/api/products', productPayload(['name' => 'Produk B', 'code' => 'RS-DUP']))
        ->assertStatus(400)
        ->assertJsonPath('error', 'The code has already been taken.');
});

test('a product keeps its own code on update but cannot take another code', function () {
    $first = Product::create(productPayload(['code' => 'RS-ONE']));
    Product::create(productPayload(['name' => 'Produk B', 'code' => 'RS-TWO']));

    $this->putJson("/api/products/{$first->id}", productPayload(['code' => 'RS-ONE', 'stock' => 5]))
        ->assertOk()
        ->assertJsonPath('code', 'RS-ONE');

    $this->putJson("/api/products/{$first->id}", productPayload(['code' => 'RS-TWO']))
        ->assertStatus(400);
});

test('check-code reports availability and can ignore the product being edited', function () {
    $product = Product::create(productPayload(['code' => 'RS-TAKEN']));

    $this->getJson('/api/products/check-code?code=RS-FREE')
        ->assertOk()
        ->assertExactJson(['code' => 'RS-FREE', 'available' => true]);

    $this->getJson('/api/products/check-code?code=RS-TAKEN')
        ->assertOk()
        ->assertJsonPath('available', false);

    $this->getJson("/api/products/check-code?code=RS-TAKEN&ignoreId={$product->id}")
        ->assertOk()
        ->assertJsonPath('available', true);

    $this->getJson('/api/products/check-code')
        ->assertStatus(400)
        ->assertJsonPath('error', 'The code field is required.');
});

test('by-code returns the product with its category or 404', function () {
    $product = Product::create(productPayload(['code' => 'https://example.com/p/1']));

    $this->getJson('/api/products/by-code?code=' . urlencode('https://example.com/p/1'))
        ->assertOk()
        ->assertJsonPath('id', $product->id)
        ->assertJsonPath('category.name', 'Keramik');

    $this->getJson('/api/products/by-code?code=UNKNOWN')
        ->assertStatus(404)
        ->assertJsonPath('error', 'Product not found');
});

test('the product list includes the code and can be searched by it', function () {
    Product::create(productPayload(['code' => 'RS-FINDME']));
    Product::create(productPayload(['name' => 'Produk B', 'code' => 'RS-OTHER']));

    $this->getJson('/api/products?page=1&orderBy=product_name&order=asc&quickFilter=FINDME')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.code', 'RS-FINDME');
});

test('code endpoints require authentication', function () {
    auth()->forgetGuards();
    app('auth')->guard('sanctum')->forgetUser();

    $this->withHeader('Authorization', 'Bearer invalid')
        ->getJson('/api/products/check-code?code=X')
        ->assertStatus(401);
});
