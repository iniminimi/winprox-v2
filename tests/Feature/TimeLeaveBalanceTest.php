<?php

declare(strict_types=1);

use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\RequestAbsenceAction;
use App\Actions\Time\SetWorkerLeaveEntitlementAction;
use App\Actions\Time\WorkerLeaveBalanceAction;
use App\Data\Time\DecideAbsenceRequestData;
use App\Data\Time\RequestAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Time\AbsenceRequestsIndex;
use App\Models\AuditLog;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerLeaveEntitlement;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function leaveBalanceWorld(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
    ]);
    $leave = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'VL',
        'label' => 'Verlof',
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $team, $worker, $leave];
}

it('telt roosterverlof, een blok met uren en een open aanvraag', function () {
    [$tenant, , $team, $worker, $leave] = leaveBalanceWorld();
    app(SetWorkerLeaveEntitlementAction::class)->handle($tenant, $worker, 2026, '2', 2027, null, null);

    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-10',
        'shift_type_id' => $leave->id,
        'kind' => ShiftTypeKind::Leave,
        'status' => PlannedShiftStatus::Published,
        'break_minutes' => 0,
    ]);
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-11',
        'shift_type_id' => $leave->id,
        'kind' => ShiftTypeKind::Leave,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'status' => PlannedShiftStatus::Published,
        'break_minutes' => 0,
    ]);
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-11-12', '2026-11-12'),
    );
    ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'RC',
        'label' => 'Recup',
        'kind' => ShiftTypeKind::Recup,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'is_active' => true,
    ]);
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Recup, '2026-11-13', '2026-11-13'),
    );

    $balance = app(WorkerLeaveBalanceAction::class)->handle($tenant, [(int) $worker->id], 2026)[(int) $worker->id];

    expect($balance->entitlementHalves)->toBe(4)
        ->and($balance->approvedHalves)->toBe(3)
        ->and($balance->plannedHalves)->toBe(2)
        ->and($balance->remainingHalves())->toBe(-1);

    app()->setLocale('nl');
    $snapshot = app(ListRosterWeekAction::class)->handle((int) $tenant->id, '2026-11-09', (int) $team->id);
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);

    expect($row['leave_balance']['short'])->toBe('−0,5 d')
        ->and($row['leave_balance']['title'])->toBe("Verlofsaldo 2026\nRecht 2\nGoedgekeurd 1,5\nGepland 1\nResterend −0,5");
});

it('telt een halve aanvraag één keer na goedkeuren', function () {
    [$tenant, $admin, , $worker] = leaveBalanceWorld();
    app(SetWorkerLeaveEntitlementAction::class)->handle($tenant, $worker, 2026, '5', 2027, '1,5', null);

    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-11-16', '2026-11-16', null, true),
    );

    $before = app(WorkerLeaveBalanceAction::class)->handle($tenant, [(int) $worker->id], 2026)[(int) $worker->id];
    expect($before->plannedHalves)->toBe(1)->and($before->approvedHalves)->toBe(0);

    app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(true, ''),
        $admin,
    );

    $shift = PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', '2026-11-16')->first();
    $after = app(WorkerLeaveBalanceAction::class)->handle($tenant, [(int) $worker->id], 2026)[(int) $worker->id];

    expect($request->fresh()?->status)->toBe(AbsenceRequestStatus::Approved)
        ->and((float) $shift?->day_portion)->toBe(0.5)
        ->and($after->approvedHalves)->toBe(1)
        ->and($after->plannedHalves)->toBe(0)
        ->and((float) WorkerLeaveEntitlement::query()->where('worker_id', $worker->id)->where('year', 2027)->value('days'))->toBe(1.5);
});

it('telt een open aanvraag niet op een dag die al verlof op het rooster heeft', function () {
    [$tenant, , , $worker, $leave] = leaveBalanceWorld();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-18',
        'shift_type_id' => $leave->id,
        'kind' => ShiftTypeKind::Leave,
        'status' => PlannedShiftStatus::Draft,
        'break_minutes' => 0,
    ]);
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-11-18', '2026-11-19'),
    );

    $balance = app(WorkerLeaveBalanceAction::class)->handle($tenant, [(int) $worker->id], 2026)[(int) $worker->id];

    expect($balance->approvedHalves)->toBe(2)
        ->and($balance->plannedHalves)->toBe(2)
        ->and($balance->remainingHalves())->toBeNull();
});

it('splitst een aanvraag over de jaargrens en wist een leeg recht', function () {
    [$tenant, $admin, , $worker] = leaveBalanceWorld();
    $set = app(SetWorkerLeaveEntitlementAction::class);
    $set->handle($tenant, $worker, 2026, '4', 2027, '4', $admin->id);
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-12-31', '2027-01-01'),
    );

    $action = app(WorkerLeaveBalanceAction::class);
    expect($action->handle($tenant, [(int) $worker->id], 2026)[(int) $worker->id]->plannedHalves)->toBe(2)
        ->and($action->handle($tenant, [(int) $worker->id], 2027)[(int) $worker->id]->plannedHalves)->toBe(2);

    $set->handle($tenant, $worker, 2026, '', 2027, '', $admin->id);

    expect(WorkerLeaveEntitlement::query()->where('worker_id', $worker->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'worker.leave_entitlement_set')->count())->toBe(2);
});

it('weigert een halve dag over meerdere datums en toont een tekort', function () {
    [$tenant, $admin, , $worker] = leaveBalanceWorld();
    app(SetWorkerLeaveEntitlementAction::class)->handle($tenant, $worker, 2026, '0', 2027, null, null);
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-11-20', '2026-11-20'),
    );

    expect(fn () => app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-11-23', '2026-11-24', null, true),
    ))->toThrow(\InvalidArgumentException::class, 'half_day_range');

    Livewire::actingAs($admin)
        ->test(AbsenceRequestsIndex::class)
        ->call('openDecide', $request->id)
        ->assertSee(__('time.leave_balance.shortfall', ['days' => '−1']), false);
});
