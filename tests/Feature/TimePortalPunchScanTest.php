<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Portal\ClockPointScanGrant;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function punchScanTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'punch-scan-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $clockPoint, $worker];
}

function signInPunchScanWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('klokt in na een verse QR-scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'))
        ->assertSee(__('time.portal.clock.scan_required_hint'), false);

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue()
        ->and(ClockPointScanGrant::isValid($clockPoint->id))->toBeFalse();
});

it('weigert een tweede prik op dezelfde open tab zonder nieuwe scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'))
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});

it('laat uitklokken na een nieuwe QR-scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'));

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.clocked_out'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('weigert inklokken wanneer de scan-grant verlopen is', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = signInPunchScanWorker($clockPoint);
    expect(ClockPointScanGrant::isValid($clockPoint->id))->toBeTrue();

    $this->travel(11)->minutes();

    $portal->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeFalse();
});

it('laat pauzes zonder nieuwe scan', function () {
    [, $clockPoint, $worker] = punchScanTenant();

    $portal = signInPunchScanWorker($clockPoint)
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'));

    $portal->call('startBreak')
        ->assertSet('flashMessage', __('time.portal.break_started'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->first()?->openBreak)->not->toBeNull();
});

it('eist een verse scan om aanwezigheid te verplaatsen', function () {
    [$tenant, $clockA, $worker] = punchScanTenant();
    $clockB = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'punch-scan-b-'.$tenant->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockA);

    $portal = Livewire::test(TimePortal::class, ['token' => $clockB->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.transferred'));

    $portal->call('clockOut')
        ->assertSet('flashMessage', __('time.portal.errors.scan_required'));

    expect((int) WorkShift::query()->where('worker_id', $worker->id)->open()->value('presence_clock_point_id'))
        ->toBe($clockB->id);
});
