<?php

declare(strict_types=1);

use App\Actions\Time\CompareRosterAttendanceAction;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterAttendanceStatus;
use App\Enums\ShiftTypeKind;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

afterEach(fn () => Tenancy::forget());

function attendanceTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    return [$tenant, $worker];
}

function plannedWork(Tenant $tenant, Worker $worker, string $date): PlannedShift
{
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
    ]);
}

it('toont deviation meteen bij late inklok vandaag', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 20:45:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse('2026-09-15 20:45:00'),
        'clock_out_at' => null,
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe(RosterAttendanceStatus::Deviation->value);
});

it('toont geen missing vandaag vóór het geplande einde', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 10:00:00');
    $planned = plannedWork($tenant, $worker, $today);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result)->not->toHaveKey($worker->id.':'.$today);
});

it('toont missing vandaag na het geplande einde zonder prik', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 17:00:00');
    $planned = plannedWork($tenant, $worker, $today);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe(RosterAttendanceStatus::Missing->value);
});

it('laat een stipt gestarte open dienst vandaag met rust', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 12:00:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse('2026-09-15 09:00:00'),
        'clock_out_at' => null,
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe(RosterAttendanceStatus::Ok->value);
});

it('toont missing op een vorige dag zonder prik', function () {
    [$tenant, $worker] = attendanceTenant();
    $date = '2026-09-14';
    $now = Carbon::parse('2026-09-15 10:00:00');
    $planned = plannedWork($tenant, $worker, $date);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$date],
        $now,
    );

    expect($result[$worker->id.':'.$date] ?? null)->toBe(RosterAttendanceStatus::Missing->value);
});

it('telt inklok binnen de 15-minutenmarge als ok', function (string $clockIn, string $expected) {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 12:00:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($today.' '.$clockIn.':00'),
        'clock_out_at' => null,
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe($expected);
})->with([
    'stipt' => ['09:00', RosterAttendanceStatus::Ok->value],
    '5 min te vroeg' => ['08:55', RosterAttendanceStatus::Ok->value],
    '15 min te vroeg' => ['08:45', RosterAttendanceStatus::Ok->value],
    '16 min te vroeg' => ['08:44', RosterAttendanceStatus::Deviation->value],
    '14 min te laat' => ['09:14', RosterAttendanceStatus::Ok->value],
    '15 min te laat' => ['09:15', RosterAttendanceStatus::Deviation->value],
]);

it('telt uitklok binnen de 15-minutenmarge als ok', function (string $clockOut, string $expected) {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 18:00:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($today.' 09:00:00'),
        'clock_out_at' => Carbon::parse($today.' '.$clockOut.':00'),
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe($expected);
})->with([
    'stipt' => ['17:00', RosterAttendanceStatus::Ok->value],
    '15 min te vroeg' => ['16:45', RosterAttendanceStatus::Ok->value],
    '16 min te vroeg' => ['16:44', RosterAttendanceStatus::Deviation->value],
    '14 min te laat' => ['17:14', RosterAttendanceStatus::Ok->value],
    '15 min te laat' => ['17:15', RosterAttendanceStatus::Deviation->value],
]);

it('laat een open dienst tot het einde plus 15 minuten met rust', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 17:14:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($today.' 09:00:00'),
        'clock_out_at' => null,
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe(RosterAttendanceStatus::Ok->value);
});

it('toont deviation als een dienst 15 minuten na het einde nog open is', function () {
    [$tenant, $worker] = attendanceTenant();
    $today = '2026-09-15';
    $now = Carbon::parse('2026-09-15 17:15:00');
    $planned = plannedWork($tenant, $worker, $today);

    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($today.' 09:00:00'),
        'clock_out_at' => null,
    ]);

    $result = app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        new Collection([$planned]),
        [(int) $worker->id],
        [$today],
        $now,
    );

    expect($result[$worker->id.':'.$today] ?? null)->toBe(RosterAttendanceStatus::Deviation->value);
});
