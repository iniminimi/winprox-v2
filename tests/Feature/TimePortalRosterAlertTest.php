<?php

declare(strict_types=1);

use App\Actions\Time\ResolveWorkerPortalRosterAlertsAction;
use App\Enums\PlannedShiftStatus;
use App\Enums\PortalRosterClockAlert;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkShiftStatus;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function rosterAlertTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Noord',
        'qr_token' => 'roster-alert-clock-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $team, $clockPoint, $worker];
}

function publishWorkShift(Tenant $tenant, Worker $worker, string $date, string $start = '09:00', string $end = '17:00'): PlannedShift
{
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => $start,
        'end_time' => $end,
        'break_minutes' => 0,
    ]);
}

function signInRosterAlertWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('markeert niet-ingeklokt vanaf de geplande start', function () {
    [$tenant, , , $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');

    $alerts = app(ResolveWorkerPortalRosterAlertsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        ['2026-09-22'],
        Carbon::parse('2026-09-22 09:00:00'),
    );

    expect($alerts['2026-09-22'] ?? null)->toBe(PortalRosterClockAlert::Absent);
});

it('toont geen alert vóór de geplande start', function () {
    [$tenant, , , $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');

    $alerts = app(ResolveWorkerPortalRosterAlertsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        ['2026-09-22'],
        Carbon::parse('2026-09-22 08:59:00'),
    );

    expect($alerts)->toBe([]);
});

it('markeert te laat vanaf 15 minuten na de start', function () {
    [$tenant, $team, , $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_at' => Carbon::parse('2026-09-22 09:15:00'),
        'status' => WorkShiftStatus::Open,
    ]);

    $alerts = app(ResolveWorkerPortalRosterAlertsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        ['2026-09-22'],
        Carbon::parse('2026-09-22 10:00:00'),
    );

    expect($alerts['2026-09-22'] ?? null)->toBe(PortalRosterClockAlert::Late);
});

it('telt 14 minuten te laat nog als ok', function () {
    [$tenant, $team, , $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_at' => Carbon::parse('2026-09-22 09:14:00'),
        'status' => WorkShiftStatus::Open,
    ]);

    $alerts = app(ResolveWorkerPortalRosterAlertsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        ['2026-09-22'],
        Carbon::parse('2026-09-22 10:00:00'),
    );

    expect($alerts)->toBe([]);
});

it('negeert afwezigheid, concept en toekomstige dagen', function () {
    [$tenant, , , $worker] = rosterAlertTenant();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-22',
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
    ]);
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'status' => PlannedShiftStatus::Draft,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
    ]);
    publishWorkShift($tenant, $worker, '2026-09-24');

    $alerts = app(ResolveWorkerPortalRosterAlertsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        ['2026-09-22', '2026-09-23', '2026-09-24'],
        Carbon::parse('2026-09-22 12:00:00'),
    );

    expect($alerts)->toBe([]);
});

it('toont de rode driehoek op Clock Point naast niet ingeklokt', function () {
    [$tenant, , $clockPoint, $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');
    $this->travelTo(Carbon::parse('2026-09-22 10:00:00'));

    signInRosterAlertWorker($clockPoint)
        ->assertSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertSee(__('time.portal.clock_alert.absent'), false)
        ->assertSeeHtml('wp-portal-clock-alert');
});

it('toont geen driehoek vóór de geplande start op Clock Point', function () {
    [$tenant, , $clockPoint, $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');
    $this->travelTo(Carbon::parse('2026-09-22 08:00:00'));

    signInRosterAlertWorker($clockPoint)
        ->assertSee(__('time.portal.clock.not_clocked_in'), false)
        ->assertDontSee(__('time.portal.clock_alert.absent'), false)
        ->assertDontSeeHtml('wp-portal-clock-alert');
});

it('toont de rode driehoek bij te late inklok in Mijn uren', function () {
    [$tenant, $team, $clockPoint, $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22', '08:00', '16:00');
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => Carbon::parse('2026-09-22 08:20:00'),
        'clock_out_at' => Carbon::parse('2026-09-22 16:00:00'),
        'status' => WorkShiftStatus::Closed,
    ]);
    $this->travelTo(Carbon::parse('2026-09-22 17:00:00'));

    signInRosterAlertWorker($clockPoint)
        ->call('openHours')
        ->assertSee('08:20', false)
        ->assertSee(__('time.portal.clock_alert.late'), false)
        ->assertSeeHtml('wp-portal-clock-alert');
});

it('toont de rode driehoek naast de geplande uren in Mijn rooster', function () {
    [$tenant, , $clockPoint, $worker] = rosterAlertTenant();
    publishWorkShift($tenant, $worker, '2026-09-22');
    $this->travelTo(Carbon::parse('2026-09-22 10:00:00'));

    signInRosterAlertWorker($clockPoint)
        ->call('openSchedule')
        ->assertSee('09:00-17:00', false)
        ->assertSee(__('time.portal.clock_alert.absent'), false)
        ->assertSeeHtml('wp-portal-clock-alert');
});
