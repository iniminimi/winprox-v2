<?php

declare(strict_types=1);

use App\Actions\Time\RequestAbsenceAction;
use App\Actions\Time\WorkerRecupBalanceAction;
use App\Data\Time\RequestAbsenceData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Time\OvertimeIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\WorkDurationFormatter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

function recupBalanceTenant(): array
{
    Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00'));

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'weekly_contract_minutes' => 2280,
    ]);

    return [$tenant, $clockPoint, $worker];
}

function recupWorked(Tenant $tenant, Worker $worker, ClockPoint $clockPoint, string $in, string $out): void
{
    WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => Carbon::parse($in),
        'clock_out_at' => Carbon::parse($out),
        'total_break_minutes' => 0,
    ]);
}

it('telt gewerkt min gepubliceerd werk als overuren', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-21',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-21',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => PlannedShiftStatus::Draft,
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => PlannedShiftStatus::Published,
    ]);
    recupWorked($tenant, $worker, $clockPoint, '2026-09-21 08:00:00', '2026-09-21 17:00:00');
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => Carbon::parse('2026-09-22 08:00:00'),
        'status' => \App\Enums\WorkShiftStatus::Open,
    ]);

    expect(app(WorkerRecupBalanceAction::class)->handle($tenant, $worker))->toBe(60);
});

it('trekt gepubliceerde recup en een open aanvraag af', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => ShiftTypeKind::Recup,
        'is_active' => true,
    ]);
    recupWorked($tenant, $worker, $clockPoint, '2026-09-21 08:00:00', '2026-09-21 17:00:00');
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-21',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'kind' => ShiftTypeKind::Recup,
        'start_time' => null,
        'end_time' => null,
        'status' => PlannedShiftStatus::Published,
    ]);
    foreach ([1 => 456, 2 => 456, 3 => 456, 4 => 400, 5 => 456, 6 => 0, 7 => 0] as $weekday => $minutes) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
            'weekday' => $weekday,
            'minutes' => $minutes,
        ]);
    }
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Recup, '2026-09-24', '2026-09-24'),
    );

    // 540 gewerkt − 480 gepland − 456 (woensdag, standaardpatroon) − 400 (donderdag, patroon)
    expect(app(WorkerRecupBalanceAction::class)->handle($tenant, $worker))->toBe(60 - 456 - 400);
});

it('telt een open recupaanvraag niet dubbel op een dag die al recup is', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => ShiftTypeKind::Recup,
        'is_active' => true,
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'kind' => ShiftTypeKind::Recup,
        'start_time' => '08:00',
        'end_time' => '10:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Recup, '2026-09-23', '2026-09-23'),
    );

    expect(app(WorkerRecupBalanceAction::class)->handle($tenant, $worker))->toBe(-120);
});

it('telt een hele recupdag als nul zonder patroon en zonder contract', function () {
    [$tenant, , $worker] = recupBalanceTenant();
    $worker->forceFill(['weekly_contract_minutes' => null])->save();
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'kind' => ShiftTypeKind::Recup,
        'start_time' => null,
        'end_time' => null,
        'status' => PlannedShiftStatus::Published,
    ]);

    expect(app(WorkerRecupBalanceAction::class)->handle($tenant, $worker->fresh()))->toBe(0);
});

it('weigert een uitvoerder van een andere organisatie', function () {
    [$tenant, , $worker] = recupBalanceTenant();
    $other = Tenant::factory()->create(['has_time_module' => true]);

    expect(fn () => app(WorkerRecupBalanceAction::class)->handle($other, $worker))
        ->toThrow(InvalidArgumentException::class, 'worker_tenant_mismatch');
});

it('aanvaardt een expliciete peildatum', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    recupWorked($tenant, $worker, $clockPoint, '2026-09-21 08:00:00', '2026-09-21 09:00:00');

    expect(app(WorkerRecupBalanceAction::class)->handle(
        $tenant,
        $worker,
        CarbonImmutable::parse('2026-09-20'),
    ))->toBe(0);
});

it('zet overuren van een vorige maand in die maand en in het totaal', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-08-03',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);
    recupWorked($tenant, $worker, $clockPoint, '2026-08-03 08:00:00', '2026-08-03 17:00:00');

    $months = app(WorkerRecupBalanceAction::class)->monthly($tenant, [(int) $worker->id]);

    expect($months[$worker->id][8])->toBe(60)
        ->and($months[$worker->id][9])->toBe(0)
        ->and(array_sum($months[$worker->id]))->toBe(60)
        ->and(app(WorkerRecupBalanceAction::class)->handle($tenant, $worker))->toBe(60);
});

it('toont in het beheer het totaal en de maand van de overuren', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    $worker->forceFill(['first_name' => 'Nora', 'last_name' => 'Peeters'])->save();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-08-03',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);
    recupWorked($tenant, $worker, $clockPoint, '2026-08-03 08:00:00', '2026-08-03 17:00:00');

    $this->actingAs($admin)
        ->get(route('time.overtime.index'))
        ->assertOk()
        ->assertSee('Nora Peeters', false)
        ->assertSeeInOrder([
            __('time.overtime.worker'),
            __('time.overtime.total'),
            'wp-overtime-table__gap',
        ], false)
        ->assertSee(__('time.filters.team'), false)
        ->assertSee(__('time.filters.clock_point'), false)
        ->assertSee(WorkDurationFormatter::signed(60), false)
        ->assertSee('wp-time-nav--clock', false)
        ->assertSee('wp-time-nav--plan', false);

    $this->actingAs($admin)
        ->get(route('time.shifts.index'))
        ->assertOk()
        ->assertSee(__('time.nav.overtime'), false)
        ->assertSee(__('time.nav.shifts'), false);
});

it('filtert overuren op team en clock point en houdt het jaarsaldo', function () {
    [$tenant, $clockPoint, $worker] = recupBalanceTenant();
    $worker->forceFill(['first_name' => 'Nora', 'last_name' => 'Peeters'])->save();
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Andere']);
    $otherPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Poort B']);
    $other = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $otherTeam->id,
        'first_name' => 'Mila',
        'last_name' => 'Janssens',
    ]);
    recupWorked($tenant, $worker, $clockPoint, '2026-08-03 08:00:00', '2026-08-03 17:00:00');
    recupWorked($tenant, $other, $otherPoint, '2026-08-03 08:00:00', '2026-08-03 17:00:00');
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(OvertimeIndex::class)
        ->assertSee('Nora Peeters', false)
        ->assertSee('Mila Janssens', false)
        ->set('teamFilter', $worker->internal_team_id)
        ->call('applyFilters')
        ->assertSee('Nora Peeters', false)
        ->assertDontSee('Mila Janssens', false)
        ->set('teamFilter', null)
        ->set('clockPointFilter', $otherPoint->id)
        ->call('applyFilters')
        ->assertDontSee('Nora Peeters', false)
        ->assertSee('Mila Janssens', false)
        ->assertSee(WorkDurationFormatter::signed(540), false);
});
