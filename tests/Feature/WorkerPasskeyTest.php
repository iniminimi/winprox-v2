<?php

declare(strict_types=1);

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkerPasskeyCredential;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;

afterEach(fn () => Tenancy::forget());

function passkeySetup(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $device = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    return [$tenant, $team, $worker, $device];
}

function passkeySession(Worker $worker, WorkerDevice $device, bool $verified = false): array
{
    $session = [
        'wp_portal_device_token' => $device->device_token,
        'wp_portal_remembered_worker_'.(int) $worker->internal_team_id => (int) $worker->id,
    ];
    if ($verified) {
        $session['wp_portal_verified_team_'.(int) $worker->internal_team_id] = [
            'worker_id' => (int) $worker->id,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    return $session;
}

function passkeyB64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * ES256-sleutelpaar + COSE-publieke sleutel, zoals een platform-authenticator die levert.
 *
 * @return array{0: \OpenSSLAsymmetricKey, 1: string}
 */
function fakePasskeyKeypair(): array
{
    $key = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $details = openssl_pkey_get_details($key);
    $x = str_pad((string) $details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad((string) $details['ec']['y'], 32, "\0", STR_PAD_LEFT);

    $cose = (string) MapObject::create()
        ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
        ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
        ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
        ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
        ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

    return [$key, base64_encode($cose)];
}

function passkeyAssertionPayload(string $credentialIdB64, string $challengeB64, $key, int $counter = 1, ?string $userHandle = null): array
{
    $clientDataJson = json_encode([
        'type' => 'webauthn.get',
        'challenge' => $challengeB64,
        'origin' => 'http://localhost',
        'crossOrigin' => false,
    ]);
    $authData = hash('sha256', 'localhost', true).chr(0x05).pack('N', $counter);
    openssl_sign($authData.hash('sha256', $clientDataJson, true), $signature, $key, OPENSSL_ALGO_SHA256);

    return [
        'id' => $credentialIdB64,
        'rawId' => $credentialIdB64,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => passkeyB64($clientDataJson),
            'authenticatorData' => passkeyB64($authData),
            'signature' => passkeyB64($signature),
            'userHandle' => $userHandle !== null ? passkeyB64($userHandle) : null,
        ],
    ];
}

it('weigert register-options zonder geverifieerde sessie', function () {
    [, , $worker, $device] = passkeySetup();

    $this->withSession(passkeySession($worker, $device))
        ->postJson(route('public.time-portal.passkey.register-options', $worker->tenant_id))
        ->assertForbidden();
});

it('geeft register-options voor een geverifieerde worker', function () {
    [, , $worker, $device] = passkeySetup();

    $response = $this->withSession(passkeySession($worker, $device, verified: true))
        ->postJson(route('public.time-portal.passkey.register-options', $worker->tenant_id))
        ->assertOk();

    expect($response->json('challenge'))->toBeString()->not->toBe('')
        ->and($response->json('rp.id'))->toBe('localhost')
        ->and($response->json('user.id'))->toBeString()
        ->and($response->json('authenticatorSelection.userVerification'))->toBe('required');
});

it('weigert registratie van een rommel-payload', function () {
    [, , $worker, $device] = passkeySetup();

    $session = passkeySession($worker, $device, verified: true);
    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.register-options', $worker->tenant_id))
        ->assertOk();

    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.register', $worker->tenant_id), [
            'credential' => '{"id":"rommel"}',
        ])
        ->assertStatus(422);

    expect(WorkerPasskeyCredential::query()->where('worker_id', $worker->id)->exists())->toBeFalse();
});

it('weigert assert-options als de worker geen passkey heeft', function () {
    [, , $worker, $device] = passkeySetup();

    $this->withSession(passkeySession($worker, $device))
        ->postJson(route('public.time-portal.passkey.assert-options', $worker->tenant_id))
        ->assertNotFound();
});

it('geeft assert-options met allowCredentials voor een worker met passkey', function () {
    [, , $worker, $device] = passkeySetup();
    [, $cose] = fakePasskeyKeypair();
    WorkerPasskeyCredential::query()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'credential_id' => passkeyB64(random_bytes(32)),
        'credential_public_key' => $cose,
        'user_handle' => 'wp-worker-'.$worker->id,
        'transports' => ['internal'],
        'sign_count' => 0,
    ]);

    $response = $this->withSession(passkeySession($worker, $device))
        ->postJson(route('public.time-portal.passkey.assert-options', $worker->tenant_id))
        ->assertOk();

    expect($response->json('allowCredentials'))->toHaveCount(1)
        ->and($response->json('userVerification'))->toBe('required')
        ->and($response->json('rpId'))->toBe('localhost');
});

it('weigert een assertion zonder uitgegeven challenge', function () {
    [, , $worker, $device] = passkeySetup();

    $this->withSession(passkeySession($worker, $device))
        ->postJson(route('public.time-portal.passkey.assert', $worker->tenant_id), [
            'credential' => '{}',
        ])
        ->assertStatus(422);
});

it('weigert endpoints voor een andere tenant dan de sessie-worker', function () {
    [$tenant, , $worker, $device] = passkeySetup();
    $otherTenant = Tenant::factory()->create();

    $this->withSession(passkeySession($worker, $device, verified: true))
        ->postJson(route('public.time-portal.passkey.register-options', $otherTenant->id))
        ->assertForbidden();

    $this->withSession(passkeySession($worker, $device))
        ->postJson(route('public.time-portal.passkey.assert-options', $otherTenant->id))
        ->assertForbidden();
});

it('verifieert een echte assertion en markeert de portalsessie', function () {
    [$tenant, $team, $worker, $device] = passkeySetup();
    [$key, $cose] = fakePasskeyKeypair();
    $credentialId = passkeyB64(random_bytes(32));
    $userHandle = 'wp-worker-'.$worker->id;
    $credential = WorkerPasskeyCredential::query()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'credential_id' => $credentialId,
        'credential_public_key' => $cose,
        'user_handle' => $userHandle,
        'transports' => ['internal'],
        'sign_count' => 0,
    ]);

    $session = passkeySession($worker, $device);
    $options = $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert-options', $tenant->id))
        ->assertOk()
        ->json();

    $payload = passkeyAssertionPayload($credentialId, $options['challenge'], $key, counter: 7, userHandle: $userHandle);

    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert', $tenant->id), [
            'credential' => json_encode($payload),
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($credential->fresh()->sign_count)->toBe(7)
        ->and($credential->fresh()->last_used_at)->not->toBeNull()
        ->and(WorkerVerification::verifiedWorker($team)?->id)->toBe($worker->id);
});

it('gebruikt een challenge maar één keer', function () {
    [$tenant, , $worker, $device] = passkeySetup();
    [$key, $cose] = fakePasskeyKeypair();
    $credentialId = passkeyB64(random_bytes(32));
    WorkerPasskeyCredential::query()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'credential_id' => $credentialId,
        'credential_public_key' => $cose,
        'user_handle' => 'wp-worker-'.$worker->id,
        'transports' => ['internal'],
        'sign_count' => 0,
    ]);

    $session = passkeySession($worker, $device);
    $options = $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert-options', $tenant->id))
        ->assertOk()
        ->json();

    $payload = passkeyAssertionPayload($credentialId, $options['challenge'], $key);

    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert', $tenant->id), [
            'credential' => json_encode($payload),
        ])
        ->assertOk();

    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert', $tenant->id), [
            'credential' => json_encode($payload),
        ])
        ->assertStatus(422);
});

it('weigert een assertion voor een credential van een andere worker', function () {
    [$tenant, , $worker, $device] = passkeySetup();
    [$key, $cose] = fakePasskeyKeypair();
    WorkerPasskeyCredential::query()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'credential_id' => passkeyB64(random_bytes(32)),
        'credential_public_key' => $cose,
        'user_handle' => 'wp-worker-'.$worker->id,
        'transports' => ['internal'],
        'sign_count' => 0,
    ]);
    $other = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $worker->internal_team_id,
        'field_icon_slug' => 'star',
    ]);
    $foreignCredentialId = passkeyB64(random_bytes(32));
    WorkerPasskeyCredential::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $other->id,
        'credential_id' => $foreignCredentialId,
        'credential_public_key' => $cose,
        'user_handle' => 'wp-worker-'.$other->id,
        'transports' => ['internal'],
        'sign_count' => 0,
    ]);

    $session = passkeySession($worker, $device);
    $options = $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert-options', $tenant->id))
        ->assertOk()
        ->json();

    $payload = passkeyAssertionPayload($foreignCredentialId, $options['challenge'], $key);
    $this->withSession($session)
        ->postJson(route('public.time-portal.passkey.assert', $tenant->id), [
            'credential' => json_encode($payload),
        ])
        ->assertStatus(422);
});
