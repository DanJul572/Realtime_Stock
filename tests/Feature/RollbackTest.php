<?php

use App\Http\Middleware\LogFailedRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => 1]));
});

test('a request that crashes after writing rolls back its changes but keeps the error log', function () {
    Route::middleware('api')->post('/api/test-crash-after-write', function () {
        Category::create(['name' => 'Granit']);
        throw new RuntimeException('Boom');
    });

    $this->postJson('/api/test-crash-after-write')->assertStatus(500);

    expect(Category::count())->toBe(0)
        ->and(AuditLog::where('auditable_type', 'category')->count())->toBe(0)
        ->and(ErrorLog::sole())
        ->status_code->toBe(500)
        ->exception_message->toBe('Boom');
});

test('a failing audit log rolls back the change it belongs to', function () {
    $category = Category::create(['name' => 'Keramik']);
    AuditLog::creating(fn () => throw new RuntimeException('Audit insert failed'));

    $this->putJson("/api/categories/{$category->id}", ['name' => 'Granit'])->assertStatus(500);

    expect($category->fresh()->name)->toBe('Keramik')
        ->and(ErrorLog::sole()->exception_message)->toBe('Audit insert failed');
});

test('successful writes are committed', function () {
    $this->postJson('/api/categories', ['name' => 'Granit'])->assertCreated();

    expect(Category::sole()->name)->toBe('Granit')
        ->and(AuditLog::where('auditable_type', 'category')->count())->toBe(1);
});

test('an error log that cannot be stored is written to a text file', function () {
    $storage = sys_get_temp_dir() . '/realstock-test-' . uniqid();
    $this->app->useStoragePath($storage);
    ErrorLog::creating(fn () => throw new RuntimeException('Database is down'));

    $this->getJson('/api/does-not-exist')->assertStatus(404);

    $content = File::get(LogFailedRequest::fallbackPath());
    expect(ErrorLog::count())->toBe(0)
        ->and($content)->toContain('"status_code": 404')
        ->and($content)->toContain('/api/does-not-exist')
        ->and($content)->toContain('RuntimeException: Database is down');

    File::deleteDirectory($storage);
});
