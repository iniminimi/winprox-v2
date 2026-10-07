<?php

declare(strict_types=1);

use App\Actions\Time\CopyRosterWeeksAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SyncWorkerUnavailabilitiesAction;
use App\Data\Time\CopyRosterWeeksData;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerUnavailability;
use App\Support\Tenancy;
use Carbon\Carbon;

afterEach(fn () => Tenancy::forget());

function unavailTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $team, $worker];
}

function unavailCells(Worker $worker, string $weekStart, array $values = []): array
{
    $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $values[$worker->id.':'.$date] ?? '',
        ];
    }

    return $cells;
}

it('synchroniseert terugkerende weekdag-onbeschikbaarheid', function () {
    [$tenant, , , $worker] = unavailTenant();
    $sync = app(SyncWorkerUnavailabilitiesAction::class);

    $sync->handle($worker, [1, 3, 7, 9, 'vrijdag']);
    expect($worker->unavailabilities()->pluck('weekday')->sort()->values()->all())
        ->toBe([1, 3, 7]);

    $sync->handle($worker, [2]);
    expect($worker->unavailabilities()->pluck('weekday')->all())->toBe([2]);

    $sync->handle($worker, []);
    expect($worker->unavailabilities()->count())->toBe(0);
});

it('markeert grid-cellen op onbeschikbare weekdagen', function () {
    [$tenant, , $team, $worker] = unavailTenant();
    WorkerUnavailability::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 6, // zaterdag
    ]);

    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $saturday = Carbon::parse($week)->addDays(5)->toDateString();
    $sunday = Carbon::parse($week)->addDays(6)->toDateString();

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        $week,
        (int) $team->id,
    )->toArray();

    expect($snapshot['unavailable'][$worker->id.':'.$saturday] ?? null)->toBeTrue()
        ->and($snapshot['unavailable'][$worker->id.':'.$sunday] ?? null)->toBeNull()
        ->and($snapshot['unavailable'][$worker->id.':'.$week] ?? null)->toBeNull();
});

it('waarschuwt bij opslaan op een onbeschikbare dag maar blokkeert niet', function () {
    [$tenant, $admin, , $worker] = unavailTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $wednesday = Carbon::parse($week)->addDays(2)->toDateString();

    WorkerUnavailability::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 3,
    ]);

    $result = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], unavailCells($worker, $week, [
            $worker->id.':'.$wednesday => '18:00-20:00',
        ])),
        $admin->id,
    );

    expect($result['shifts'])->toHaveCount(1)
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0]['date'])->toBe($wednesday)
        ->and($result['warnings'][0]['worker_id'])->toBe((int) $worker->id);

    expect(PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $wednesday)->exists())->toBeTrue();
});

it('waarschuwt niet voor afwezigheidsblokken op een onbeschikbare dag', function () {
    [$tenant, $admin, , $worker] = unavailTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $wednesday = Carbon::parse($week)->addDays(2)->toDateString();

    WorkerUnavailability::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 3,
    ]);

    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Leave),
        $admin->id,
    );

    $result = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], unavailCells($worker, $week, [
            $worker->id.':'.$wednesday => 'VL',
        ])),
        $admin->id,
    );

    expect($result['shifts'])->toHaveCount(1)
        ->and($result['warnings'])->toHaveCount(0);
});

it('waarschuwt in de dag-editor bij een werkblok op een onbeschikbare dag', function () {
    [$tenant, $admin, , $worker] = unavailTenant();
    $wednesday = now()->addWeek()->startOfWeek(Carbon::MONDAY)->addDays(2)->toDateString();

    WorkerUnavailability::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 3,
    ]);

    $result = app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData($worker->id, $wednesday, [
            ['start_time' => '18:00', 'end_time' => '20:00', 'break_minutes' => 0],
        ]),
        $admin->id,
    );

    expect($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0]['date'])->toBe($wednesday);
});

it('slaat gekopieerde shifts over die op een onbeschikbare weekdag vallen', function () {
    [$tenant, $admin, , $worker] = unavailTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $target = Carbon::parse($week)->addWeek()->toDateString();
    $wednesday = Carbon::parse($week)->addDays(2)->toDateString();

    WorkerUnavailability::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 3,
    ]);

    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 0,
    ]);
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $wednesday,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '18:00',
        'end_time' => '20:00',
        'break_minutes' => 0,
    ]);

    $report = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 1, [$worker->id]),
        $admin->id,
    );

    $weekReport = $report['weeks'][0];
    expect($weekReport['status'])->toBe(CopyRosterWeeksAction::STATUS_OK)
        ->and($weekReport['created'])->toBe(1)
        ->and($weekReport['skipped'])->toHaveCount(1)
        ->and($weekReport['skipped'][0]['reason'])->toBe('unavailable')
        ->and($weekReport['skipped'][0]['date'])->toBe(Carbon::parse($wednesday)->addWeek()->toDateString());
});
