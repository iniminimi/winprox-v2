<?php

declare(strict_types=1);

use App\Actions\Time\CopyRosterWeeksAction;
use App\Data\Time\CopyRosterWeeksData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;

afterEach(fn () => Tenancy::forget());

function copyRangeTenant(): array
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

    return [$tenant, $admin, $worker];
}

function copyRangeBlock(
    Tenant $tenant,
    Worker $worker,
    string $date,
    string $start = '07:00',
    string $end = '15:00',
    ShiftTypeKind $kind = ShiftTypeKind::Work,
): PlannedShift {
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => $kind,
        'start_time' => $kind === ShiftTypeKind::Work ? $start : null,
        'end_time' => $kind === ShiftTypeKind::Work ? $end : null,
        'break_minutes' => 0,
    ]);
}

it('kopieert een bronweek naar meerdere opeenvolgende weken', function () {
    [$tenant, $admin, $worker] = copyRangeTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $target = Carbon::parse($week)->addWeek()->toDateString();

    $noted = copyRangeBlock($tenant, $worker, $week);
    $noted->update(['description' => 'Mag niet meekopiëren']);
    copyRangeBlock($tenant, $worker, Carbon::parse($week)->addDays(2)->toDateString(), '18:00', '20:00');
    copyRangeBlock($tenant, $worker, Carbon::parse($week)->addDay()->toDateString(), '00:00', '00:00', ShiftTypeKind::Sick);

    $report = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 3, [$worker->id]),
        $admin->id,
    );

    expect($report['created_total'])->toBe(6)
        ->and(array_column($report['weeks'], 'status'))->toBe([
            CopyRosterWeeksAction::STATUS_OK,
            CopyRosterWeeksAction::STATUS_OK,
            CopyRosterWeeksAction::STATUS_OK,
        ]);

    for ($i = 1; $i <= 3; $i++) {
        $weekStart = Carbon::parse($week)->addWeeks($i);
        expect(
            PlannedShift::where('worker_id', $worker->id)
                ->where('kind', ShiftTypeKind::Work->value)
                ->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
                ->where('status', PlannedShiftStatus::Draft->value)
                ->count(),
        )->toBe(2);
        expect(
            PlannedShift::where('worker_id', $worker->id)
                ->where('kind', ShiftTypeKind::Sick->value)
                ->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
                ->count(),
        )->toBe(0);
    }

    // Notities zijn datumgebonden en worden niet meegekopieerd.
    expect(
        PlannedShift::where('worker_id', $worker->id)
            ->whereDate('work_date', $target)
            ->value('description'),
    )->toBeNull();
});

it('slaat een niet-lege doelweek over zonder de andere weken te blokkeren', function () {
    [$tenant, $admin, $worker] = copyRangeTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $target = Carbon::parse($week)->addWeek()->toDateString();

    copyRangeBlock($tenant, $worker, $week);
    copyRangeBlock($tenant, $worker, Carbon::parse($week)->addWeek()->toDateString());

    $report = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 2, [$worker->id]),
        $admin->id,
    );

    expect($report['weeks'][0]['status'])->toBe(CopyRosterWeeksAction::STATUS_OCCUPIED)
        ->and($report['weeks'][0]['created'])->toBe(0)
        ->and($report['weeks'][1]['status'])->toBe(CopyRosterWeeksAction::STATUS_OK)
        ->and($report['weeks'][1]['created'])->toBe(1)
        ->and($report['created_total'])->toBe(1);
});

it('slaat shifts over die op een goedgekeurde afwezigheidsdag vallen', function () {
    [$tenant, $admin, $worker] = copyRangeTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $target = Carbon::parse($week)->addWeek()->toDateString();
    $wednesday = Carbon::parse($week)->addDays(2)->toDateString();

    copyRangeBlock($tenant, $worker, $week);
    copyRangeBlock($tenant, $worker, $wednesday, '18:00', '20:00');

    AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => Carbon::parse($wednesday)->addWeek()->toDateString(),
        'date_to' => Carbon::parse($wednesday)->addWeek()->toDateString(),
        'status' => AbsenceRequestStatus::Approved,
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
        ->and($weekReport['skipped'][0]['reason'])->toBe('absence')
        ->and($weekReport['skipped'][0]['date'])->toBe(Carbon::parse($wednesday)->addWeek()->toDateString())
        ->and($weekReport['skipped'][0]['label'])->toBe('18:00-20:00');
});

it('rapporteert de bronweek als doelweek', function () {
    [$tenant, $admin, $worker] = copyRangeTenant();
    $week = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    copyRangeBlock($tenant, $worker, $week);

    $report = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $week, 2, [$worker->id]),
        $admin->id,
    );

    expect($report['weeks'][0]['status'])->toBe(CopyRosterWeeksAction::STATUS_SAME_WEEK)
        ->and($report['weeks'][1]['status'])->toBe(CopyRosterWeeksAction::STATUS_OK)
        ->and($report['created_total'])->toBe(1);
});
