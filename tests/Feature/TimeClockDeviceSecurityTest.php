<?php

declare(strict_types=1);

use App\Actions\Portal\AttachWorkerDeviceAction;
use App\Actions\Time\AssertWorkerClockDeviceAction;
use App\Actions\Time\ClearWorkerClockDeviceAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\ConfirmWorkerClockPinAction;
use App\Actions\Time\SetWorkerClockPinAction;
use App\Actions\Time\TransferOpenWorkShiftToClockPointAction;
use App\Enums\TimePresenceAttentionType;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Tenancy;
use App\Support\Time\TimePresenceAttentionRules;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function clockSecurityTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $team, $clockPoint, $worker];
}

it('koppelt het eerste toestel bij inklokken en weigert een tweede met auditlog', function () {
    [, , $clockPoint, $worker] = clockSecurityTenant();

    $deviceA = app(AttachWorkerDeviceAction::class)->handle($worker);
    $deviceAModel = $worker->devices()->where('device_token', $deviceA['device_token'])->first();

    app(ClockInAction::class)->handle(
        $worker,
        $clockPoint,
        $deviceAModel,
        enforceClockDevice: true,
        requestDeviceToken: $deviceA['device_token'],
    );

    expect((int) $worker->fresh()->clock_device_id)->toBe((int) $deviceAModel->id);

    $deviceB = app(AttachWorkerDeviceAction::class)->handle($worker);
    $deviceBModel = $worker->devices()->where('device_token', $deviceB['device_token'])->first();

    expect(fn () => app(ClockInAction::class)->handle(
        $worker->fresh(),
        $clockPoint,
        $deviceBModel,
        enforceClockDevice: true,
        requestDeviceToken: $deviceB['device_token'],
    ))->toThrow(InvalidArgumentException::class, 'clock_device_mismatch');

    expect(DB::table('audit_logs')->where('action', 'worker.clock_device_refused')->where('model_id', $worker->id)->exists())->toBeTrue();
});

it('laat opnieuw inklokken nadat beheer het toestel vrijgeeft', function () {
    [, , $clockPoint, $worker] = clockSecurityTenant();

    $first = app(AttachWorkerDeviceAction::class)->handle($worker);
    $firstDevice = $worker->devices()->where('device_token', $first['device_token'])->first();
    app(ClockInAction::class)->handle(
        $worker,
        $clockPoint,
        $firstDevice,
        enforceClockDevice: true,
        requestDeviceToken: $first['device_token'],
    );

    app(\App\Actions\Time\ClockOutAction::class)->handle($worker, $clockPoint);
    app(ClearWorkerClockDeviceAction::class)->handle($worker->fresh(), (int) $worker->tenant_id);

    $second = app(AttachWorkerDeviceAction::class)->handle($worker->fresh());
    $secondDevice = $worker->fresh()->devices()->where('device_token', $second['device_token'])->first();

    $shift = app(ClockInAction::class)->handle(
        $worker->fresh(),
        $clockPoint,
        $secondDevice,
        enforceClockDevice: true,
        requestDeviceToken: $second['device_token'],
    );

    expect((int) $worker->fresh()->clock_device_id)->toBe((int) $secondDevice->id)
        ->and($shift->clock_in_device_id)->toBe($secondDevice->id);
});

it('slaagt een 4-cijferige pincode op gehashed en bevestigt die', function () {
    [, , , $worker] = clockSecurityTenant();

    app(SetWorkerClockPinAction::class)->handle($worker, '1234', (int) $worker->tenant_id);
    $fresh = $worker->fresh();

    expect($fresh->hasClockPin())->toBeTrue()
        ->and($fresh->clock_pin_hash)->not->toBe('1234');

    expect(app(ConfirmWorkerClockPinAction::class)->handle($fresh, '1234', (int) $fresh->tenant_id))->not->toBeNull()
        ->and(app(ConfirmWorkerClockPinAction::class)->handle($fresh, '0000', (int) $fresh->tenant_id))->toBeNull();
});

it('toont een alarm bij een hop binnen 5 minuten', function () {
    [$tenant, , $clockA, $worker] = clockSecurityTenant();
    $clockB = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    app(ClockInAction::class)->handle($worker, $clockA);
    $shift = app(TransferOpenWorkShiftToClockPointAction::class)->handle($worker, $clockB);

    $items = TimePresenceAttentionRules::collect(collect([$shift->fresh()]));

    expect($items)->toHaveCount(1)
        ->and($items->first()->type)->toBe(TimePresenceAttentionType::RapidHop);
});

it('bewaart gps bij inklokken zonder te weigeren als gps ontbreekt', function () {
    [, , $clockPoint, $worker] = clockSecurityTenant();

    $withGps = app(ClockInAction::class)->handle(
        $worker,
        $clockPoint,
        latitude: 50.85,
        longitude: 4.35,
    );
    expect($withGps->clock_in_latitude)->toEqual(50.85)
        ->and($withGps->clock_in_longitude)->toEqual(4.35);

    app(\App\Actions\Time\ClockOutAction::class)->handle($worker, $clockPoint);

    $workerB = Worker::factory()->create([
        'tenant_id' => $worker->tenant_id,
        'internal_team_id' => $worker->internal_team_id,
    ]);
    $withoutGps = app(ClockInAction::class)->handle($workerB, $clockPoint);
    expect($withoutGps->clock_in_latitude)->toBeNull();
});

it('klokt in via het portaal na icoon en toont het icoon niet meer op het welkomstscherm', function () {
    [, , $clockPoint, $worker] = clockSecurityTenant();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertDontSeeHtml('wp-icon-tile is-selected')
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'));

    expect((int) $worker->fresh()->clock_device_id)->toBeGreaterThan(0);
});

it('weigert aanmelden met dezelfde worker op een tweede toestel', function () {
    [, , $clockPoint, $worker] = clockSecurityTenant();

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSet('flashMessage', __('portal.worker.signed_in'));

    expect($worker->fresh()->clock_device_id)->not->toBeNull();

    $this->flushSession();

    Livewire::withCookie(\App\Support\Portal\WorkerDeviceSession::DEVICE_TOKEN_COOKIE, 'second-phone-token')
        ->test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSet('flashMessage', __('time.portal.errors.device_mismatch'));

    expect(DB::table('audit_logs')->where('action', 'worker.clock_device_refused')->where('model_id', $worker->id)->exists())->toBeTrue();
});

it('koppelt geen gsm bij API-inkloken', function () {
    [$tenant, , $clockPoint, $worker] = clockSecurityTenant();
    $user = \App\Models\User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/time/clock-in', [
            'worker_id' => $worker->id,
            'clock_point_id' => $clockPoint->id,
        ])
        ->assertCreated();

    expect($worker->fresh()->clock_device_id)->toBeNull();
});

it('geeft een gekoppeld toestel vrij via de API met time:write', function () {
    [$tenant, , $clockPoint, $worker] = clockSecurityTenant();

    $device = app(AttachWorkerDeviceAction::class)->handle($worker);
    $deviceModel = $worker->devices()->where('device_token', $device['device_token'])->first();
    app(ClockInAction::class)->handle(
        $worker,
        $clockPoint,
        $deviceModel,
        enforceClockDevice: true,
        requestDeviceToken: $device['device_token'],
    );

    $user = \App\Models\User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/time/workers/'.$worker->id.'/release-clock-device')
        ->assertOk();

    expect($worker->fresh()->clock_device_id)->toBeNull();
});
