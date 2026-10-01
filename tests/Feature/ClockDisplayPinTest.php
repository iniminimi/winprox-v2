<?php

declare(strict_types=1);

use App\Actions\Time\ConfirmClockDisplayClaimAction;
use App\Actions\Time\IssueClockDisplayPairingCodeAction;
use App\Actions\Time\SetWorkerClockPinAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Enums\WorkShiftStatus;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

afterEach(function () {
    Tenancy::forget();
    Carbon::setTestNow();
});

function pinTenant(): Tenant
{
    return Tenant::factory()->create(['has_time_module' => true]);
}

/**
 * Koppelt een scherm aan een Clock Point en geeft [point, device_token].
 *
 * @return array{0: ClockPoint, 1: string}
 */
function pairedPinPoint(Tenant $tenant, ?int $locationId = null): array
{
    $point = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationId,
    ]);

    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);

    return [$point->fresh(), $claim->fresh()->issued_token];
}

function pinWorker(Tenant $tenant, ?Location $location = null, string $pin = '1234'): Worker
{
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id]);
    if ($location !== null) {
        $worker->locations()->sync([$location->id]);
    }
    app(SetWorkerClockPinAction::class)->handle($worker, $pin, $tenant->id);

    return $worker->fresh();
}

it('vereist een device-token voor de workerlijst en pin-klok', function () {
    $this->getJson('/api/v1/time/clock-displays/workers')->assertUnauthorized();
    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => 1,
        'pin' => '1234',
    ])->assertUnauthorized();
});

it('lijst enkel actieve workers met PIN op de locatie van het Clock Point', function () {
    $tenant = pinTenant();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    [$point, $token] = pairedPinPoint($tenant, $location->id);

    $inge = pinWorker($tenant, $location);
    pinWorker($tenant, $location, '5678');
    // Geen PIN → niet in de trie.
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    // Andere locatie → buiten scope.
    pinWorker($tenant, $otherLocation);
    // Inactief → buiten scope.
    $inactive = pinWorker($tenant, $location, '9999');
    $inactive->forceFill(['is_active' => false])->save();
    // Geen locatie gekoppeld → mag overal klokken → wél in de lijst.
    $everywhere = pinWorker($tenant, null, '4321');
    Tenancy::forget();

    $response = $this->getJson('/api/v1/time/clock-displays/workers', [
        'X-WinProx-Clock-Key' => $token,
    ])->assertOk()->json('workers');

    $ids = array_column($response, 'id');
    expect($ids)->toContain($inge->id)
        ->toContain($everywhere->id)
        ->toHaveCount(3)
        ->and($response[0])->toHaveKeys(['id', 'first_name', 'last_name'])
        ->and($response[0])->not->toHaveKey('clock_pin_hash');
});

it('klokt in en uit met een correcte PIN en auditeert de prik', function () {
    $tenant = pinTenant();
    Tenancy::actAs($tenant->id);
    [$point, $token] = pairedPinPoint($tenant);
    $worker = pinWorker($tenant);
    Tenancy::forget();

    $headers = ['X-WinProx-Clock-Key' => $token];
    $payload = ['worker_id' => $worker->id, 'pin' => '1234'];

    $this->postJson('/api/v1/time/clock-displays/pin-clock', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result', 'in')
        ->assertJsonPath('worker', $worker->displayName());

    expect(WorkShift::where('worker_id', $worker->id)
        ->where('status', WorkShiftStatus::Open)->exists())->toBeTrue();

    $this->postJson('/api/v1/time/clock-displays/pin-clock', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result', 'out');

    expect(WorkShift::where('worker_id', $worker->id)
        ->where('status', WorkShiftStatus::Closed)->exists())->toBeTrue();
    expect(AuditLog::where('action', 'worker.clock_display_punched')->count())->toBe(2);
    expect(WorkShift::where('worker_id', $worker->id)->sole()->clock_in_source->value)
        ->toBe('clock_display_pin');
});

it('weigert een foute PIN en lockt de worker na 2 pogingen voor 1 minuut', function () {
    $tenant = pinTenant();
    Tenancy::actAs($tenant->id);
    [$point, $token] = pairedPinPoint($tenant);
    $worker = pinWorker($tenant);
    $other = pinWorker($tenant, null, '9876');
    Tenancy::forget();

    $headers = ['X-WinProx-Clock-Key' => $token];

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $worker->id,
        'pin' => '0000',
    ], $headers)->assertUnauthorized()->assertJsonPath('error', 'invalid_pin');

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $worker->id,
        'pin' => '0000',
    ], $headers)->assertStatus(429)
        ->assertJsonPath('error', 'worker_locked')
        ->assertJsonPath('retry_after', fn ($v) => $v > 0 && $v <= 60);

    // Ook de juiste PIN is geblokkeerd zolang de lockout loopt.
    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $worker->id,
        'pin' => '1234',
    ], $headers)->assertStatus(429);

    // De lockout is per worker: een collega klokt gewoon door.
    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $other->id,
        'pin' => '9876',
    ], $headers)->assertOk()->assertJsonPath('result', 'in');

    expect(AuditLog::where('action', 'worker.clock_display_pin_failed')->count())->toBe(2);
    expect(AuditLog::where('action', 'worker.clock_display_pin_blocked_attempt')->exists())->toBeTrue();

    // Na de lockout-minuut werkt de juiste PIN weer (array-cache eert
    // Carbon::setTestNow via InteractsWithTime).
    Carbon::setTestNow(now()->addSeconds(61));

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $worker->id,
        'pin' => '1234',
    ], $headers)->assertOk()->assertJsonPath('result', 'in');
});

it('verbergt workers buiten scope als not_found', function () {
    $tenant = pinTenant();
    $otherTenant = pinTenant();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    [$point, $token] = pairedPinPoint($tenant, $location->id);

    $otherLocationWorker = pinWorker($tenant, $otherLocation);
    Tenancy::forget();
    Tenancy::actAs($otherTenant->id);
    $foreignWorker = pinWorker($otherTenant);
    Tenancy::forget();

    $headers = ['X-WinProx-Clock-Key' => $token];

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $otherLocationWorker->id,
        'pin' => '1234',
    ], $headers)->assertNotFound()->assertJsonPath('error', 'worker_not_found');

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $foreignWorker->id,
        'pin' => '1234',
    ], $headers)->assertNotFound();

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => 999999,
        'pin' => '1234',
    ], $headers)->assertNotFound();
});

it('valideert het PIN-formaat', function () {
    $tenant = pinTenant();
    Tenancy::actAs($tenant->id);
    [$point, $token] = pairedPinPoint($tenant);
    $worker = pinWorker($tenant);
    Tenancy::forget();

    $this->postJson('/api/v1/time/clock-displays/pin-clock', [
        'worker_id' => $worker->id,
        'pin' => 'abcd',
    ], ['X-WinProx-Clock-Key' => $token])->assertUnprocessable();
});
