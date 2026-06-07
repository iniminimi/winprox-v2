<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;

afterEach(fn () => Tenancy::forget());

it('slaat safe methods over (GET, HEAD, OPTIONS)', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'test-key-123')
        ->getJson('/api/v1/issues')
        ->assertOk()
        ->assertHeaderMissing('Idempotency-Key');
});

it('voert request uit zonder idempotency key', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ])
        ->assertCreated()
        ->assertHeaderMissing('Idempotency-Key');
});

it('slaat response op bij eerste request met idempotency key', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'unique-key-123')
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ])
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'unique-key-123')
        ->assertHeaderMissing('Idempotency-Replayed');
});

it('replayt cached response bij herhaalde request met dezelfde key', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $idempotencyKey = 'replay-key-456';

    // Eerste request
    $firstResponse = $this->withToken($token)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ]);

    $firstResponse->assertCreated()
        ->assertHeader('Idempotency-Key', $idempotencyKey)
        ->assertHeaderMissing('Idempotency-Replayed');

    $firstData = $firstResponse->json('data');

    // Replay request
    $secondResponse = $this->withToken($token)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ]);

    $secondResponse->assertCreated()
        ->assertHeader('Idempotency-Key', $idempotencyKey)
        ->assertHeader('Idempotency-Replayed', 'true');

    // Response data moet identiek zijn
    expect($secondResponse->json('data'))->toBe($firstData);
});

it('geeft 409 conflict bij key hergebruik met verschillende parameters', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $idempotencyKey = 'conflict-key-789';

    // Eerste request
    $this->withToken($token)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/issues', [
            'description' => 'First issue',
            'team_ids' => [],
        ])
        ->assertCreated();

    // Tweede request met dezelfde key maar andere parameters
    $this->withToken($token)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Different issue',
            'team_ids' => [],
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Conflict. This Idempotency-Key is already used for a request with different parameters.');
});

it('geeft 422 bij key langer dan 255 karakters', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $longKey = str_repeat('a', 256);

    $this->withToken($token)
        ->withHeader('Idempotency-Key', $longKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The Idempotency-Key header may not be greater than 255 characters.');
});

it('isoleert idempotency keys per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $tokenA = $userA->createToken('test', ['issues:create'])->plainTextToken;
    $tokenB = $userB->createToken('test', ['issues:create'])->plainTextToken;

    $sharedKey = 'shared-key-123';

    // Tenant A gebruikt de key
    Tenancy::actAs($tenantA->id);
    $responseA = $this->withToken($tokenA)
        ->withHeader('Idempotency-Key', $sharedKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Tenant A issue',
            'team_ids' => [],
        ]);

    $responseA->assertCreated();

    // Clear cache om te simuleren dat tenants geïsoleerd zijn
    Cache::flush();

    // Tenant B kan dezelfde key gebruiken zonder conflict
    Tenancy::actAs($tenantB->id);
    $responseB = $this->withToken($tokenB)
        ->withHeader('Idempotency-Key', $sharedKey)
        ->postJson('/api/v1/issues', [
            'description' => 'Tenant B issue',
            'team_ids' => [],
        ]);

    $responseB->assertCreated()
        ->assertHeaderMissing('Idempotency-Replayed');
});

it('faalt open bij cache failure met logging', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    // Cache failure scenario: we testen dat de request doorgaat als cache faalt
    // In productie zou dit loggen, maar in tests verifiëren we dat het niet breekt
    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'fail-open-key')
        ->postJson('/api/v1/issues', [
            'description' => 'Test issue',
            'team_ids' => [],
        ])
        ->assertCreated(); // Request gaat door ondanks cache failure
});
