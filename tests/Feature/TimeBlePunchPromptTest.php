<?php

declare(strict_types=1);

use App\Actions\Time\ResolveWorkerBlePunchPromptAction;
use App\Enums\ClockDisplayMode;
use App\Enums\ClockSource;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerPasskeyCredential;
use App\Models\WorkShift;
use App\Support\Portal\ClockPointScanGrant;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

function blePromptWorld(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->forLocation($location)->create([
        'qr_token' => 'ble-prompt-'.$tenant->id,
        'display_id' => str_repeat('ab', 12),
        'display_secret' => str_repeat('cd', 16),
        'display_clock_mode' => ClockDisplayMode::Ble,
    ]);

    return [$tenant, $location, $worker, $clockPoint];
}

function blePublishedShift(Worker $worker, Location $location, string $start = '07:00', string $end = '15:00'): PlannedShift
{
    return PlannedShift::factory()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'location_id' => $location->id,
        'work_date' => '2026-11-18',
        'start_time' => $start,
        'end_time' => $end,
        'status' => PlannedShiftStatus::Published,
    ]);
}

it('vraagt bluetooth rond het begin en niet midden in de dienst', function () {
    [, $location, $worker] = blePromptWorld();
    blePublishedShift($worker, $location);
    $action = app(ResolveWorkerBlePunchPromptAction::class);

    Carbon::setTestNow(Carbon::parse('2026-11-18 06:50', (string) config('app.timezone')));
    expect($action->handle($worker, null, null, $location->id)['direction'])->toBe('in');

    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00', (string) config('app.timezone')));
    expect($action->handle($worker, null, null, $location->id))->toBeNull();
});

it('vraagt uitklokken rond het einde zolang de dienst open is', function () {
    [$tenant, $location, $worker, $clockPoint] = blePromptWorld();
    blePublishedShift($worker, $location);
    $open = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => Carbon::parse('2026-11-18 07:00', (string) config('app.timezone')),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-11-18 14:50', (string) config('app.timezone')));
    expect(app(ResolveWorkerBlePunchPromptAction::class)->handle($worker, $open, null, $location->id)['direction'])->toBe('out');
});

it('slaat afwezigheid, een andere vestiging, checkmate en een klok zonder scherm over', function () {
    [$tenant, $location, $worker] = blePromptWorld();
    $action = app(ResolveWorkerBlePunchPromptAction::class);
    Carbon::setTestNow(Carbon::parse('2026-11-18 06:50', (string) config('app.timezone')));

    $plan = blePublishedShift($worker, $location);
    $plan->update(['kind' => ShiftTypeKind::Leave]);
    expect($action->handle($worker->fresh(), null, null, $location->id))->toBeNull();

    $plan->update(['kind' => ShiftTypeKind::Work]);
    $elsewhere = Location::factory()->create(['tenant_id' => $tenant->id]);
    expect($action->handle($worker->fresh(), null, null, $elsewhere->id))->toBeNull();

    $tenant->update(['checkmate_mode' => true]);
    expect($action->handle($worker->fresh(), null, null, $location->id))->toBeNull();
    $tenant->update(['checkmate_mode' => false]);

    ClockPoint::query()->where('location_id', $location->id)->update([
        'display_id' => null,
        'display_secret' => null,
    ]);
    expect($action->handle($worker->fresh(), null, null, $location->id))->toBeNull();
});

it('blokkeert een bluetooth-prik tot de passkey opnieuw klopt', function () {
    [$tenant, , $worker, $clockPoint] = blePromptWorld();
    $tenant->update(['time_gps_on_clock' => true]);

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');

    session([
        'wp_ble_punch_pending' => $clockPoint->id,
        'wp_ble_punch_direction' => 'in',
    ]);

    $portal->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.ble.passkey_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();

    session([
        'wp_ble_passkey_ok' => 0,
        'wp_ble_presence_clock_point_id' => $clockPoint->id,
    ]);
    \App\Support\Portal\ClockPointScanGrant::grant($clockPoint->id);

    // Geen GPS: bluetooth-aanwezigheid volstaat ook als GPS-op-prik aan staat.
    $portal->call('confirmBlePasskeyAndPunch');

    $shift = WorkShift::query()->where('worker_id', $worker->id)->first();
    expect($shift)->not->toBeNull()
        ->and($shift->clock_in_source)->toBe(ClockSource::Bluetooth)
        ->and($tenant->id)->toBe($shift->tenant_id);
});

it('aanvaardt bluetooth van het scherm-clock-point ook als het portaal een ander punt is', function () {
    [$tenant, $location, $worker, $portalPoint] = blePromptWorld();
    $screenPoint = ClockPoint::factory()->forLocation($location)->create([
        'display_id' => 'k9zuar33m2plqwx7vbn4c1de',
        'display_secret' => str_repeat('ab', 32),
        'display_clock_mode' => ClockDisplayMode::Ble,
    ]);
    // Portaal-punt mag BLE tonen; scherm hoort bij een ander punt.
    $portalPoint->update([
        'display_id' => 'portalpointwithbledisp01',
        'display_secret' => str_repeat('cd', 32),
        'display_clock_mode' => ClockDisplayMode::Ble,
    ]);

    $hex = bin2hex(\App\Support\Time\ClockDisplayPresenceToken::buildBinary(
        (string) $screenPoint->display_id,
        (string) $screenPoint->display_secret,
        time(),
        7,
        1,
    ));

    $portal = Livewire::test(TimePortal::class, ['token' => $portalPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');

    $accepted = $portal->instance()->submitBlePresenceToken($hex, 'in');
    expect($accepted['ok'])->toBeTrue()
        ->and(ClockPointScanGrant::isValid($screenPoint->id))->toBeTrue()
        ->and((int) session('wp_ble_punch_pending'))->toBe($portalPoint->id)
        ->and((int) session('wp_ble_presence_clock_point_id'))->toBe($screenPoint->id);

    session(['wp_ble_passkey_ok' => $portalPoint->id]);
    $portal->call('clockIn');

    $shift = WorkShift::query()->where('worker_id', $worker->id)->first();
    expect($shift)->not->toBeNull()
        ->and((int) $shift->clock_in_clock_point_id)->toBe($screenPoint->id)
        ->and($shift->clock_in_source)->toBe(ClockSource::Bluetooth)
        ->and($tenant->id)->toBe($shift->tenant_id);
});

it('weigert bluetooth op een vestiging waar de uitvoerder niet mag prikken', function () {
    [$tenant, $homeLocation, $worker, $portalPoint] = blePromptWorld();
    $worker->locations()->sync([$homeLocation->id]);

    $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $screenPoint = ClockPoint::factory()->forLocation($otherLocation)->create([
        'display_id' => 'zzotherclockpointid00001',
        'display_secret' => str_repeat('ef', 32),
        'display_clock_mode' => ClockDisplayMode::Ble,
    ]);

    $hex = bin2hex(\App\Support\Time\ClockDisplayPresenceToken::buildBinary(
        (string) $screenPoint->display_id,
        (string) $screenPoint->display_secret,
        time(),
        8,
        1,
    ));

    $portal = Livewire::test(TimePortal::class, ['token' => $portalPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');

    $rejected = $portal->instance()->submitBlePresenceToken($hex, 'in');
    expect($rejected['ok'])->toBeFalse()
        ->and($rejected['error'])->toBe('worker_location_not_allowed');
    $portal->assertSet('flashMessage', __('time.portal.errors.worker_location_not_allowed'));

    // Teken niet verbruikt.
    $accepted = app(\App\Actions\Time\ValidateClockDisplayPresenceTokenAction::class)
        ->handle($hex, $tenant->id);
    expect($accepted['clock_point']->id)->toBe($screenPoint->id);
});

it('toont de bluetooth-vraag op het portaal rond de start', function () {
    [, $location, $worker, $clockPoint] = blePromptWorld();
    blePublishedShift($worker, $location);
    WorkerPasskeyCredential::query()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'credential_id' => 'ble-cred-'.$worker->id,
        'credential_public_key' => 'test',
        'user_handle' => 'handle',
    ]);
    Carbon::setTestNow(Carbon::parse('2026-11-18 06:50', (string) config('app.timezone')));

    $portal = Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token]);
    ClockPointScanGrant::consume($clockPoint->id);

    $portal
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSee(__('time.portal.ble.in_title'), false);
});
