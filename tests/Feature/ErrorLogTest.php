<?php

use App\Models\ErrorLog;
use App\Models\User;
use App\Support\LogSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('successful requests are not logged', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));

    $this->getJson('/api/test')->assertOk();
    $this->postJson('/api/categories', ['name' => 'Granit'])->assertCreated();

    expect(ErrorLog::count())->toBe(0);
});

test('a validation error is logged with the password masked', function () {
    $this->postJson('/api/login', ['email' => 'bukan-email', 'password' => 'rahasia123'])
        ->assertStatus(400);

    $log = ErrorLog::sole();
    expect($log->status_code)->toBe(400)
        ->and($log->method)->toBe('POST')
        ->and($log->url)->toEndWith('/api/login')
        ->and($log->message)->toBe('The email field must be a valid email address.')
        ->and($log->request_body)->toEqual(['email' => 'bukan-email', 'password' => LogSanitizer::MASK])
        ->and($log->user_id)->toBeNull()
        ->and($log->trace)->toBeNull();
});

test('unauthenticated requests are logged as 401', function () {
    $this->getJson('/api/products')->assertStatus(401);

    expect(ErrorLog::sole())
        ->status_code->toBe(401)
        ->message->toBe('Unauthorized')
        ->exception_class->toBe(Illuminate\Auth\AuthenticationException::class);
});

test('unknown routes are logged as 404 with the user', function () {
    $user = User::factory()->create(['role_id' => 1]);
    Sanctum::actingAs($user);

    $this->getJson('/api/does-not-exist')->assertStatus(404);
    $this->getJson('/api/products/999')->assertStatus(404);

    $logs = ErrorLog::orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('status_code')->all())->toBe([404, 404])
        ->and($logs[1]->user_id)->toBe($user->id);
});

test('forbidden requests are logged as 403', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 2]));

    $this->getJson('/api/error-logs')->assertStatus(403);

    expect(ErrorLog::sole()->status_code)->toBe(403);
});

test('server errors are logged with the exception and trace', function () {
    Route::get('/api/test-crash', fn () => throw new RuntimeException('Boom'));

    $this->getJson('/api/test-crash')->assertStatus(500);

    $log = ErrorLog::sole();
    expect($log->status_code)->toBe(500)
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->exception_message)->toBe('Boom')
        ->and($log->exception_location)->toStartWith('tests/Feature/ErrorLogTest.php:')
        ->and($log->trace)->not->toBeEmpty();
});

test('product images in a failed request are stored as a marker', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));
    $image = 'data:image/jpeg;base64,' . str_repeat('B', 8192);

    // No name, so validation fails.
    $this->postJson('/api/products', ['image' => $image])->assertStatus(400);

    expect(ErrorLog::sole()->request_body['image'])->toBe(LogSanitizer::describeImage($image));
});

test('admins can list and read error logs', function () {
    $this->getJson('/api/products')->assertStatus(401);
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));

    $page = $this->getJson('/api/error-logs?orderBy=created_at&order=desc&page=1')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status_code', 401)
        ->assertJsonMissingPath('data.0.trace');

    $this->getJson('/api/error-logs/' . $page->json('data.0.id'))
        ->assertOk()
        ->assertJsonPath('message', 'Unauthorized');

    $this->getJson('/api/error-logs?quickFilter=401')->assertOk()->assertJsonPath('total', 1);
});
