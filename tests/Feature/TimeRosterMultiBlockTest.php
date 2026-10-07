<?php

declare(strict_types=1);

use App\Actions\Time\CompareRosterAttendanceAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Exceptions\RosterValidationException;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

afterEach(fn () => Tenancy::forget());

function multiBlockTenant(): array
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

function plannedBlock(
    Tenant $tenant,
    Worker $worker,
    string $date,
    string $start,
    string $end,
    PlannedShiftStatus $status = PlannedShiftStatus::Published,
    ShiftTypeKind $kind = ShiftTypeKind::Work,
): PlannedShift {
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => $status,
        'kind' => $kind,
        'start_time' => $kind === ShiftTypeKind::Work ? $start : null,
        'end_time' => $kind === ShiftTypeKind::Work ? $end : null,
        'break_minutes' => 0,
    ]);
}

function clockSession(Worker $worker, string $in, ?string $out): WorkShift
{
    return WorkShift::factory()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($in),
        'clock_out_at' => $out !== null ? Carbon::parse($out) : null,
    ]);
}

function attendanceFor(Tenant $tenant, Collection $shifts, Worker $worker, string $date, Carbon $now): array
{
    return app(CompareRosterAttendanceAction::class)->handle(
        (int) $tenant->id,
        $shifts,
        [(int) $worker->id],
        [$date],
        $now,
    );
}

it('maakt meerdere blokken op één dag via SavePlannedDayAction', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    $result = app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['start_time' => '07:00', 'end_time' => '10:00'],
            ['start_time' => '18:00', 'end_time' => '20:00'],
        ]),
        $admin->id,
    );

    expect($result['created'])->toBe(2)
        ->and(PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('toont multi-blok-dagen regel per blok, ook op de print', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();
    $type = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'D1',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'is_active' => true,
    ]);

    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
    ]);
    plannedBlock($tenant, $worker, $date, '18:00', '20:00');

    $week = Carbon::parse($date)->startOfWeek()->toDateString();
    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id, $week, null, $admin, 'week', true, null, null, true, false,
    );

    $cell = $snapshot->cells[$worker->id.':'.$date] ?? null;
    expect($cell['multi'])->toBeTrue()
        ->and($cell['display'])->toBe("D1\n1800-2000");

    $this->actingAs($admin)
        ->get(route('time.schedule.print', ['week' => $week, 'view' => 'week']))
        ->assertOk()
        ->assertSee("D1\n1800-2000", false)
        // Uren van type-codes staan in de print-legenda.
        ->assertSee('08:00–17:00', false);
});

it('synct blokken id-aware: update, create en delete in één save', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    $keep = plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Draft);
    $drop = plannedBlock($tenant, $worker, $date, '18:00', '20:00', PlannedShiftStatus::Draft);

    $result = app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $keep->id, 'start_time' => '07:00', 'end_time' => '11:00'],
            ['start_time' => '19:00', 'end_time' => '21:00'],
        ]),
        $admin->id,
    );

    expect($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(1)
        ->and($result['deleted'])->toBe(1);

    $kept = PlannedShift::find($keep->id);
    expect($kept->end_time)->toBe('11:00')
        ->and(PlannedShift::find($drop->id))->toBeNull()
        ->and(PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('weigert overlappende blokken op één dag', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    expect(fn () => app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['start_time' => '07:00', 'end_time' => '10:00'],
            ['start_time' => '09:30', 'end_time' => '12:00'],
        ]),
        $admin->id,
    ))->toThrow(RosterValidationException::class);
});

it('laat afwezigheidsblok naast werkblok op dezelfde dag toe', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();
    $sick = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('ZK', 'Ziek', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Sick),
        $admin->id,
    );

    $result = app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['start_time' => '07:00', 'end_time' => '10:00'],
            ['shift_type_id' => $sick->id],
        ]),
        $admin->id,
    );

    expect($result['created'])->toBe(2)
        ->and(
            PlannedShift::where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->where('kind', ShiftTypeKind::Sick->value)
                ->count(),
        )->toBe(1);
});

it('weigert blok-ids van een andere dag of worker', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    [$otherTenant, , $otherWorker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();
    $foreign = plannedBlock($otherTenant, $otherWorker, $date, '07:00', '10:00');

    expect(fn () => app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $foreign->id, 'start_time' => '07:00', 'end_time' => '10:00'],
        ]),
        $admin->id,
    ))->toThrow(RosterValidationException::class);
});

it('behoudt multi-blok-dagen via keep bij grid-save', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = Carbon::parse(now())->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $day = Carbon::parse($week)->toDateString();

    $a = plannedBlock($tenant, $worker, $day, '07:00', '10:00', PlannedShiftStatus::Draft);
    $b = plannedBlock($tenant, $worker, $day, '18:00', '20:00', PlannedShiftStatus::Draft);

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => Carbon::parse($week)->addDays($i)->toDateString(),
            'raw' => '',
        ];
    }
    foreach ($cells as &$cell) {
        if ($cell['date'] === $day) {
            $cell['keep'] = true;
        }
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        $admin->id,
    );

    expect(PlannedShift::find($a->id))->not->toBeNull()
        ->and(PlannedShift::find($b->id))->not->toBeNull();
});

it('laat beide blokken ok bij één lange sessie over het gat en meldt gap', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $morning = plannedBlock($tenant, $worker, $date, '07:00', '10:00');
    $evening = plannedBlock($tenant, $worker, $date, '18:00', '20:00');
    clockSession($worker, '2026-09-15 07:00:00', '2026-09-15 20:00:00');

    $result = attendanceFor($tenant, new Collection([$morning, $evening]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'][(int) $morning->id])->toBe('ok')
        ->and($entry['blocks'][(int) $evening->id])->toBe('ok')
        ->and($entry['gap_minutes'])->toBe(480)
        ->and($entry['status'])->toBe('gap');
});

it('markeert een sessie in het gat tussen blokken als unplanned', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $morning = plannedBlock($tenant, $worker, $date, '07:00', '10:00');
    $evening = plannedBlock($tenant, $worker, $date, '18:00', '20:00');
    clockSession($worker, '2026-09-15 07:00:00', '2026-09-15 10:00:00');
    clockSession($worker, '2026-09-15 18:00:00', '2026-09-15 20:00:00');
    clockSession($worker, '2026-09-15 12:00:00', '2026-09-15 13:00:00');

    $result = attendanceFor($tenant, new Collection([$morning, $evening]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'][(int) $morning->id])->toBe('ok')
        ->and($entry['blocks'][(int) $evening->id])->toBe('ok')
        ->and($entry['unplanned_sessions'])->toBe(1)
        ->and($entry['status'])->toBe('unplanned');
});

it('dekt één blok met meerdere sessies via eerste-in en laatste-uit', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $block = plannedBlock($tenant, $worker, $date, '07:00', '15:00');
    clockSession($worker, '2026-09-15 07:05:00', '2026-09-15 12:00:00');
    clockSession($worker, '2026-09-15 12:30:00', '2026-09-15 15:10:00');

    $result = attendanceFor($tenant, new Collection([$block]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'][(int) $block->id])->toBe('ok')
        ->and($entry['status'])->toBe('ok');
});

it('markeert één ontbrekend blok als missing terwijl het andere ok is', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $morning = plannedBlock($tenant, $worker, $date, '07:00', '10:00');
    $evening = plannedBlock($tenant, $worker, $date, '18:00', '20:00');
    clockSession($worker, '2026-09-15 07:00:00', '2026-09-15 10:00:00');

    $result = attendanceFor($tenant, new Collection([$morning, $evening]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'][(int) $morning->id])->toBe('ok')
        ->and($entry['blocks'][(int) $evening->id])->toBe('missing')
        ->and($entry['status'])->toBe('missing');
});

it('telt omgezette afwezigheidsblokken niet mee als missing', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $work = plannedBlock($tenant, $worker, $date, '07:00', '10:00');
    $sick = plannedBlock($tenant, $worker, $date, '18:00', '20:00', PlannedShiftStatus::Published, ShiftTypeKind::Sick);
    clockSession($worker, '2026-09-15 07:00:00', '2026-09-15 10:00:00');

    $result = attendanceFor($tenant, new Collection([$work, $sick]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'])->toBe([(int) $work->id => 'ok'])
        ->and($entry['status'])->toBe('ok');
});

it('behandelt een volledig afwezige dag als ok zonder blok-statussen', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $sick = plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Published, ShiftTypeKind::Sick);

    $result = attendanceFor($tenant, new Collection([$sick]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['status'])->toBe('ok')
        ->and($entry['blocks'])->toBe([]);
});

it('markeert de dag als unplanned bij een prik op een volledig afwezige dag', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $sick = plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Published, ShiftTypeKind::Sick);
    clockSession($worker, '2026-09-15 09:00:00', '2026-09-15 10:00:00');

    $result = attendanceFor($tenant, new Collection([$sick]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['status'])->toBe('unplanned')
        ->and($entry['unplanned_sessions'])->toBe(1)
        ->and($entry['blocks'])->toBe([]);
});

it('markeert één te laat gestart blok als deviation terwijl het andere ok is', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $morning = plannedBlock($tenant, $worker, $date, '07:00', '10:00');
    $evening = plannedBlock($tenant, $worker, $date, '18:00', '20:00');
    clockSession($worker, '2026-09-15 07:00:00', '2026-09-15 10:00:00');
    clockSession($worker, '2026-09-15 18:30:00', '2026-09-15 20:00:00');

    $result = attendanceFor($tenant, new Collection([$morning, $evening]), $worker, $date, $now);
    $entry = $result[$worker->id.':'.$date];

    expect($entry['blocks'][(int) $morning->id])->toBe('ok')
        ->and($entry['blocks'][(int) $evening->id])->toBe('deviation')
        ->and($entry['status'])->toBe('deviation');
});

it('negeert draft-blokken in de attendance-vergelijking', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = '2026-09-15';
    $now = Carbon::parse('2026-09-16 08:00:00');

    $draft = plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Draft);

    $result = attendanceFor($tenant, new Collection([$draft]), $worker, $date, $now);

    expect($result)->not->toHaveKey($worker->id.':'.$date);
});

it('sla een notitie op bij een blok en behoudt hem bij update', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['start_time' => '07:00', 'end_time' => '10:00', 'description' => 'Extra sanitair controleren'],
        ]),
        $admin->id,
    );

    $block = PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->sole();
    expect($block->description)->toBe('Extra sanitair controleren');

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $block->id, 'start_time' => '07:00', 'end_time' => '11:00', 'description' => 'Extra sanitair'],
        ]),
        $admin->id,
    );

    $block->refresh();
    expect($block->description)->toBe('Extra sanitair')
        ->and($block->end_time)->toBe('11:00');
});

it('behoudt id en notitie bij een grid-save van een ongewijzigde cel', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = Carbon::parse(now())->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $day = Carbon::parse($week)->toDateString();

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $day, [
            ['start_time' => '07:00', 'end_time' => '10:00', 'description' => 'Sanitair'],
        ]),
        $admin->id,
    );
    $block = PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $day)->sole();

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => Carbon::parse($week)->addDays($i)->toDateString(),
            'raw' => '',
        ];
    }
    foreach ($cells as &$cell) {
        if ($cell['date'] === $day) {
            $cell['raw'] = '07:00-10:00';
        }
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        $admin->id,
    );

    $block->refresh();
    expect($block->description)->toBe('Sanitair')
        ->and(PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $day)->sole()->id)
        ->toBe($block->id);
});

it('meldt RosterChanged bij een notitie-wijziging op een published dag', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Published);
    $block = PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->sole();

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $block->id, 'start_time' => '07:00', 'end_time' => '10:00', 'description' => 'Sleutel meenemen'],
        ]),
        $admin->id,
    );

    expect(
        WorkerNotification::where('worker_id', $worker->id)
            ->where('type', WorkerNotificationType::RosterChanged->value)
            ->where('reference_id', $date)
            ->count(),
    )->toBe(1);
});

it('meldt RosterChanged bij wijziging van een published dag en dedupliceert', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Published);

    $existing = PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->first();
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $existing->id, 'start_time' => '07:00', 'end_time' => '11:00'],
        ]),
        $admin->id,
    );
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $existing->id, 'start_time' => '07:00', 'end_time' => '12:00'],
        ]),
        $admin->id,
    );

    $notifications = WorkerNotification::query()
        ->where('worker_id', $worker->id)
        ->where('type', WorkerNotificationType::RosterChanged->value)
        ->where('reference_id', $date)
        ->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->read_at)->toBeNull();
});

it('meldt niets bij een correctie op een verleden dag of draft-wijziging', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $past = now()->subWeek()->startOfWeek()->toDateString();
    $pastRow = plannedBlock($tenant, $worker, $past, '07:00', '10:00', PlannedShiftStatus::Published);
    $futureDraft = now()->addWeek()->startOfWeek()->addDay()->toDateString();
    $draftRow = plannedBlock($tenant, $worker, $futureDraft, '07:00', '10:00', PlannedShiftStatus::Draft);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $past, [
            ['id' => $pastRow->id, 'start_time' => '07:00', 'end_time' => '11:00'],
        ]),
        $admin->id,
    );
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $futureDraft, [
            ['id' => $draftRow->id, 'start_time' => '07:00', 'end_time' => '11:00'],
        ]),
        $admin->id,
    );

    expect(
        WorkerNotification::where('type', WorkerNotificationType::RosterChanged->value)->count(),
    )->toBe(0);
});

it('meldt RosterChanged via grid-save bij gewijzigde published dag', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = Carbon::parse(now())->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $day = Carbon::parse($week)->toDateString();

    plannedBlock($tenant, $worker, $day, '07:00', '10:00', PlannedShiftStatus::Published);

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => Carbon::parse($week)->addDays($i)->toDateString(),
            'raw' => '',
        ];
    }
    foreach ($cells as &$cell) {
        if ($cell['date'] === $day) {
            $cell['raw'] = '07:00-11:00';
        }
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        $admin->id,
    );

    expect(
        WorkerNotification::where('worker_id', $worker->id)
            ->where('type', WorkerNotificationType::RosterChanged->value)
            ->where('reference_id', $day)
            ->count(),
    )->toBe(1);
});
