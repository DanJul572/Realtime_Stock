<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Admin',
        'email' => 'admin@real.com',
        'password' => 'rahasia123',
        'role_id' => 1,
    ]);
});

function loginLogs()
{
    return AuditLog::where('auditable_type', 'login')->orderBy('id')->get();
}

test('a successful login is audited', function () {
    $this->postJson('/api/login', ['email' => 'admin@real.com', 'password' => 'rahasia123'])
        ->assertOk();

    $log = loginLogs()->sole();
    expect($log->event)->toBe('login')
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->auditable_id)->toBe($this->user->id)
        ->and($log->label)->toBe('Admin')
        ->and($log->new_values)->toEqual(['email' => 'admin@real.com'])
        ->and($log->ip_address)->not->toBeNull();
});

test('a wrong password is audited as a failed login of that user', function () {
    $this->postJson('/api/login', ['email' => 'admin@real.com', 'password' => 'salah'])
        ->assertStatus(400);

    $log = loginLogs()->sole();
    expect($log->event)->toBe('login_failed')
        ->and($log->auditable_id)->toBe($this->user->id)
        ->and($log->label)->toBe('Admin')
        ->and(json_encode($log->toArray()))->not->toContain('salah');
});

test('an unknown email is audited as a failed login without a user', function () {
    $this->postJson('/api/login', ['email' => 'siapa@real.com', 'password' => 'apa saja'])
        ->assertStatus(400);

    $log = loginLogs()->sole();
    expect($log->event)->toBe('login_failed')
        ->and($log->user_id)->toBeNull()
        ->and($log->auditable_id)->toBeNull()
        ->and($log->label)->toBe('siapa@real.com');
});

test('a logout is audited', function () {
    $token = $this->postJson('/api/login', ['email' => 'admin@real.com', 'password' => 'rahasia123'])
        ->json('token');

    // A real request starts without the session user that Auth::attempt set
    // during login; without this Sanctum would reuse it with a transient token.
    session()->flush();
    auth()->forgetGuards();

    $this->withToken($token)->getJson('/api/logout')->assertOk();

    expect(loginLogs()->pluck('event')->all())->toBe(['login', 'logout'])
        ->and(loginLogs()->last()->user_id)->toBe($this->user->id);
});

test('login logs are listed and counted like the other audit types', function () {
    $token = $this->postJson('/api/login', ['email' => 'admin@real.com', 'password' => 'rahasia123'])
        ->json('token');
    $this->postJson('/api/login', ['email' => 'siapa@real.com', 'password' => 'x'])->assertStatus(400);

    $this->withToken($token)->getJson('/api/audit-logs/summary')
        ->assertOk()
        ->assertJsonPath('login', 2);

    $this->withToken($token)
        ->getJson('/api/audit-logs?type=login&orderBy=created_at&order=desc&page=1')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0.event', 'login_failed')
        ->assertJsonPath('data.0.auditable_id', null)
        ->assertJsonPath('data.1.event', 'login');
});
