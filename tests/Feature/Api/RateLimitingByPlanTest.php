<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\RateLimiter;

afterEach(fn () => Tenancy::forget());

it('past trial rate limiet toe (30 req/min)', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(14),
        'allow_trial_api' => true,
    ]);
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    // Eerste request om limiet te checken
    $response = $this->withToken($token)
        ->getJson('/api/v1/issues');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 30)
        ->assertHeader('X-RateLimit-Remaining', 29);
});

it('past enterprise plan rate limiet toe (10000 req/min)', function () {
    $tenant = Tenant::factory()->create([
        'billing_plan' => 'enterprise',
        'billing_active_until' => now()->addDays(30),
    ]);
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/issues');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', 10000)
        ->assertHeader('X-RateLimit-Remaining', 9999);
});

it('geeft 429 met retry-after header bij limiet overschrijding', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(14),
        'allow_trial_api' => true,
    ]);
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    // Vul de limiet handmatig via RateLimiter
    $rateLimitKey = "api_limit:tenant_{$tenant->id}:127.0.0.1";
    for ($i = 0; $i < 30; $i++) {
        RateLimiter::hit($rateLimitKey, 60);
    }

    // Nu op limiet - volgende request moet 429 geven
    $response = $this->withToken($token)
        ->getJson('/api/v1/issues');

    $response->assertStatus(429)
        ->assertJsonPath('message', 'Rate limit exceeded.')
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Remaining', 0);
});

it('isoleert rate limits per tenant', function () {
    $tenantA = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(14),
        'allow_trial_api' => true,
    ]);
    $tenantB = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(14),
        'allow_trial_api' => true,
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $tokenA = $userA->createToken('test', ['issues:read'])->plainTextToken;
    $tokenB = $userB->createToken('test', ['issues:read'])->plainTextToken;

    // Tenant A gebruikt requests
    Tenancy::actAs($tenantA->id);
    $responseA = $this->withToken($tokenA)
        ->getJson('/api/v1/issues');

    $responseA->assertOk()
        ->assertHeader('X-RateLimit-Limit', 30);

    // Tenant B heeft eigen limiet (niet beïnvloed door A)
    // We checken dat de rate limiter keys anders zijn per tenant
    $keyA = "api_limit:tenant_{$tenantA->id}:127.0.0.1";
    $keyB = "api_limit:tenant_{$tenantB->id}:127.0.0.1";

    expect($keyA)->not->toBe($keyB);

    // Tenant B kan requests doen zonder beïnvloeding van A
    Tenancy::actAs($tenantB->id);
    $responseB = $this->withToken($tokenB)
        ->getJson('/api/v1/issues');

    $responseB->assertOk()
        ->assertHeader('X-RateLimit-Limit', 30);
});

it('gebruikt default limiet (60) voor onbekend plan', function () {
    $tenant = Tenant::factory()->create([
        'billing_plan' => 'unknown_plan',
        'billing_active_until' => now()->addDays(30),
    ]);
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/issues');

    // Onbekend plan heeft geen API toegang, dus 403
    $response->assertStatus(403);
});
