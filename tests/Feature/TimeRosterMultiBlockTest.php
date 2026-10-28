<?php

declare(strict_types=1);

use App\Actions\Time\CompareRosterAttendanceAction;
use App\Actions\Time\CorrectPlannedShiftAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SetShiftTypeActiveAction;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Livewire\Time\RosterIndex;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

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
        ->and($cell['display'])->toBe('D1 · 1800-2000');

    $this->actingAs($admin)
        ->get(route('time.schedule.print', ['week' => $week, 'view' => 'week']))
        ->assertOk()
        ->assertSee("D1\n1800-2000", false)
        // Uren van type-codes staan in de print-legenda.
        ->assertSee('08:00–17:00', false);
});

it('maakt multi-blokken aan via grid-save van een · -samenvatting (plakken)', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = Carbon::parse(now())->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $day = Carbon::parse($week)->toDateString();

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = Carbon::parse($week)->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $date === $day ? '08:00-12:00 · 13:00-17:00' : '',
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        $admin->id,
    );

    $rows = PlannedShift::query()
        ->where('worker_id', $worker->id)
        ->whereDate('work_date', $day)
        ->orderBy('start_time')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and(ShiftType::formatTime($rows[0]->start_time))->toBe('08:00')
        ->and(ShiftType::formatTime($rows[0]->end_time))->toBe('12:00')
        ->and(ShiftType::formatTime($rows[1]->start_time))->toBe('13:00')
        ->and(ShiftType::formatTime($rows[1]->end_time))->toBe('17:00');
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
        new SaveShiftTypeData('ZK', 'Ziek', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Sick),
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

it('zet een notitie-wijziging op een published dag terug op draft zonder melding', function () {
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

    expect($block->fresh()->status)->toBe(PlannedShiftStatus::Draft)
        ->and($block->fresh()->description)->toBe('Sleutel meenemen')
        ->and(WorkerNotification::where('type', WorkerNotificationType::RosterChanged->value)->count())->toBe(0);
});

it('houdt een ongewijzigd published blok published bij een day-editor save', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $date = now()->addWeek()->startOfWeek()->toDateString();

    plannedBlock($tenant, $worker, $date, '07:00', '10:00', PlannedShiftStatus::Published);

    $existing = PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $date)->first();
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $existing->id, 'start_time' => '07:00', 'end_time' => '10:00'],
        ]),
        $admin->id,
    );

    expect($existing->fresh()->status)->toBe(PlannedShiftStatus::Published);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            ['id' => $existing->id, 'start_time' => '07:00', 'end_time' => '11:00'],
        ]),
        $admin->id,
    );

    expect($existing->fresh()->status)->toBe(PlannedShiftStatus::Draft)
        ->and(WorkerNotification::where('type', WorkerNotificationType::RosterChanged->value)->count())->toBe(0);
});

it('meldt geen RosterChanged bij een correctie op een verleden dag of draft-wijziging', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $past = now()->subWeek()->startOfWeek()->toDateString();
    $pastRow = plannedBlock($tenant, $worker, $past, '07:00', '10:00', PlannedShiftStatus::Published);
    $futureDraft = now()->addWeek()->startOfWeek()->addDay()->toDateString();
    $draftRow = plannedBlock($tenant, $worker, $futureDraft, '07:00', '10:00', PlannedShiftStatus::Draft);

    // Vergrendelde dag: gewone save is verboden; correctie loopt via pad B
    // en levert RosterCorrected op, nooit RosterChanged.
    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [[
            'type' => 'update',
            'planned_shift_id' => $pastRow->id,
            'block' => ['start_time' => '07:00', 'end_time' => '11:00'],
        ]],
        'Correctie verleden dag',
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
    )->toBe(0)
        ->and(WorkerNotification::where('type', WorkerNotificationType::RosterCorrected->value)
            ->where('reference_id', $past.':'.$pastRow->id)
            ->count())->toBe(1);
});

it('zet een gewijzigde published dag via grid-save op draft zonder melding', function () {
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

    expect(PlannedShift::where('worker_id', $worker->id)->whereDate('work_date', $day)->sole()->status)
        ->toBe(PlannedShiftStatus::Draft)
        ->and(WorkerNotification::where('type', WorkerNotificationType::RosterChanged->value)->count())->toBe(0);
});

function multiNextWeek(): string
{
    return now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
}

function multiWeekCells(Worker $worker, string $week, array $values = []): array
{
    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = Carbon::parse($week)->addDays($i)->toDateString();
        $cells[] = ['worker_id' => $worker->id, 'date' => $date, 'raw' => $values[$date] ?? ''];
    }

    return $cells;
}

function multiSaveGrid(Tenant $tenant, User $admin, Worker $worker, string $week, string $raw): void
{
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], multiWeekCells($worker, $week, [$week => $raw])),
        $admin->id,
    );
}

it('behoudt een identieke multi-blokdag via de API met ids, notities en status', function (string $raw) {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $a = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    $a->update(['description' => 'Sleutel ophalen']);
    $b = plannedBlock($tenant, $worker, $week, '18:00', '20:00');
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/time/schedule', [
            'week_start' => $week,
            'worker_ids' => [$worker->id],
            'cells' => multiWeekCells($worker, $week, [$week => $raw]),
        ])
        ->assertOk();

    Tenancy::actAs($tenant->id);
    $keptA = PlannedShift::find($a->id);

    expect($keptA)->not->toBeNull()
        ->and($keptA->description)->toBe('Sleutel ophalen')
        ->and($keptA->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::find($b->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::query()->count())->toBe(2);
})->with([
    'grid-weergave' => '0700-1000 · 1800-2000',
    'met dubbelepunten' => '07:00-10:00 · 18:00-20:00',
]);

it('behoudt pauze, notitie en status van een ongewijzigde vrije-tijdcel via de API', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $row = plannedBlock($tenant, $worker, $week, '07:00', '15:00');
    $row->update(['break_minutes' => 30, 'description' => 'Opleiding']);
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/time/schedule', [
            'week_start' => $week,
            'worker_ids' => [$worker->id],
            'cells' => multiWeekCells($worker, $week, [$week => '07:00-15:00']),
        ])
        ->assertOk();

    Tenancy::actAs($tenant->id);
    $kept = PlannedShift::find($row->id);

    expect($kept)->not->toBeNull()
        ->and($kept->break_minutes)->toBe(30)
        ->and($kept->description)->toBe('Opleiding')
        ->and($kept->status)->toBe(PlannedShiftStatus::Published);
});

it('behoudt pauze en notitie van een ongewijzigde vrije-tijdcel via de grid', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $row = plannedBlock($tenant, $worker, $week, '07:00', '15:00');
    $row->update(['break_minutes' => 30, 'description' => 'Opleiding']);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('teamFilter', (string) $worker->internal_team_id)
        ->set('weekStart', $week)
        ->call('save', array_slice(multiWeekCells($worker, $week, [
            $week => '07:00-15:00',
            Carbon::parse($week)->addDay()->toDateString() => '09:00-12:00',
        ]), 0, 5))
        ->assertNotDispatched('roster-save-failed');

    Tenancy::actAs($tenant->id);
    $kept = PlannedShift::find($row->id);

    expect(PlannedShift::query()->where('start_time', '09:00')->exists())->toBeTrue()
        ->and($kept)->not->toBeNull()
        ->and($kept->break_minutes)->toBe(30)
        ->and($kept->description)->toBe('Opleiding')
        ->and($kept->status)->toBe(PlannedShiftStatus::Published);
});

it('behoudt aangepaste tijden als dezelfde code opnieuw getypt wordt', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $row = plannedBlock($tenant, $worker, $week, '08:00', '16:00');
    $row->update(['shift_type_id' => $type->id, 'description' => 'Later starten']);

    multiSaveGrid($tenant, $admin, $worker, $week, 'V');

    $kept = PlannedShift::find($row->id);
    expect($kept)->not->toBeNull()
        ->and($kept->start_time)->toBe('08:00')
        ->and($kept->end_time)->toBe('16:00')
        ->and($kept->description)->toBe('Later starten');
});

it('behoudt bij een gedeeltelijke wijziging de ongewijzigde blokken (gemengde status)', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $a = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    $a->update(['description' => 'Sleutel ophalen']);
    $b = plannedBlock($tenant, $worker, $week, '18:00', '20:00');

    multiSaveGrid($tenant, $admin, $worker, $week, '0700-1000 · 1900-2100');

    $keptA = PlannedShift::find($a->id);
    $new = PlannedShift::query()->where('start_time', '19:00')->first();

    expect($keptA)->not->toBeNull()
        ->and($keptA->status)->toBe(PlannedShiftStatus::Published)
        ->and($keptA->description)->toBe('Sleutel ophalen')
        ->and(PlannedShift::find($b->id))->toBeNull()
        ->and($new)->not->toBeNull()
        ->and($new->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('weigert een nieuw blok dat overlapt met een behouden blok', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $a = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    plannedBlock($tenant, $worker, $week, '18:00', '20:00');

    expect(fn () => multiSaveGrid($tenant, $admin, $worker, $week, '0700-1000 · 0930-1200'))
        ->toThrow(RosterValidationException::class);
    expect(PlannedShift::find($a->id))->not->toBeNull();
});

it('behoudt de koppeling met een inactief werktype bij een ongewijzigde cel', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $row = plannedBlock($tenant, $worker, $week, '07:00', '15:00');
    $row->update(['shift_type_id' => $type->id]);
    app(SetShiftTypeActiveAction::class)->handle($type, false, $admin->id);

    multiSaveGrid($tenant, $admin, $worker, $week, '07:00-15:00');

    $kept = PlannedShift::find($row->id);
    expect($kept)->not->toBeNull()
        ->and((int) $kept->shift_type_id)->toBe((int) $type->id);
});

it('weigert een ongewijzigde afwezigheid met inactief type niet', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $leave = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    $row = plannedBlock($tenant, $worker, $week, '', '', PlannedShiftStatus::Published, ShiftTypeKind::Leave);
    $row->update(['shift_type_id' => $leave->id]);
    app(SetShiftTypeActiveAction::class)->handle($leave, false, $admin->id);
    $display = $row->fresh()->load('shiftType')->displayValue();

    multiSaveGrid($tenant, $admin, $worker, $week, $display);

    expect(PlannedShift::find($row->id))->not->toBeNull();
});

it('slaat keep via de API over en verwerkt de gewijzigde raw', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $row = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;
    $cells = multiWeekCells($worker, $week, [$week => '09:00-12:00']);
    $cells[0]['keep'] = true;

    $this->withToken($token)
        ->putJson('/api/v1/time/schedule', [
            'week_start' => $week,
            'worker_ids' => [$worker->id],
            'cells' => $cells,
        ])
        ->assertOk();

    Tenancy::actAs($tenant->id);
    expect(PlannedShift::find($row->id))->toBeNull()
        ->and(PlannedShift::query()->where('start_time', '09:00')->exists())->toBeTrue();
});

it('aanvaardt de langst mogelijke eigen celweergave als raw', function () {
    $raw = implode(' · ', array_fill(0, 8, '0800-1200/ABCDEFGH'));

    expect(mb_strlen($raw))->toBe(165)
        ->and(Validator::make([
            'week_start' => '2026-09-14',
            'worker_ids' => [1],
            'cells' => [['worker_id' => 1, 'date' => '2026-09-14', 'raw' => $raw]],
        ], SavePlannedShiftsRequest::rulesFor())->passes())->toBeTrue();
});

it('weigert raw van 193 tekens en aanvaardt 192', function () {
    $rules = SavePlannedShiftsRequest::rulesFor();
    $payload = fn (string $raw) => [
        'week_start' => '2026-09-14',
        'worker_ids' => [1],
        'cells' => [['worker_id' => 1, 'date' => '2026-09-14', 'raw' => $raw]],
    ];

    expect(Validator::make($payload(str_repeat('a', 193)), $rules)->fails())->toBeTrue()
        ->and(Validator::make($payload(str_repeat('é', 193)), $rules)->fails())->toBeTrue()
        ->and(Validator::make($payload(str_repeat('a', 192)), $rules)->passes())->toBeTrue();
});

it('houdt bij een overlapfout ook het niet-gematchte gepubliceerde blok intact', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $morning = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    $morning->update(['description' => 'Ochtend', 'break_minutes' => 10]);
    $evening = plannedBlock($tenant, $worker, $week, '18:00', '20:00');
    $evening->update(['description' => 'Avond', 'break_minutes' => 15]);

    expect(fn () => multiSaveGrid($tenant, $admin, $worker, $week, '0700-1000 · 0930-1200'))
        ->toThrow(RosterValidationException::class);

    $keptMorning = PlannedShift::find($morning->id);
    $keptEvening = PlannedShift::find($evening->id);

    expect($keptMorning)->not->toBeNull()
        ->and($keptMorning->description)->toBe('Ochtend')
        ->and($keptMorning->break_minutes)->toBe(10)
        ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
        ->and($keptMorning->start_time)->toBe('07:00')
        ->and($keptMorning->end_time)->toBe('10:00')
        ->and($keptEvening)->not->toBeNull()
        ->and($keptEvening->description)->toBe('Avond')
        ->and($keptEvening->break_minutes)->toBe(15)
        ->and($keptEvening->status)->toBe(PlannedShiftStatus::Published)
        ->and($keptEvening->start_time)->toBe('18:00')
        ->and($keptEvening->end_time)->toBe('20:00')
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('houdt bij één code voor twee dezelfde blokken de vroegste rij, ongeacht aanmaakvolgorde', function (string $raw, string $createFirst, string $keptStart, string $keptNote, int $keptBreak, PlannedShiftStatus $keptStatus) {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
    ]);
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $make = function (string $start, string $end, PlannedShiftStatus $status, string $note, int $break) use ($tenant, $worker, $week, $type, $unit, $location): PlannedShift {
        $row = plannedBlock($tenant, $worker, $week, $start, $end, $status);
        $row->update([
            'shift_type_id' => $type->id,
            'unit_id' => $unit->id,
            'unit_code' => 'G1',
            'unit_name' => 'Groep 1',
            'location_id' => $location->id,
            'description' => $note,
            'break_minutes' => $break,
        ]);

        return $row->fresh();
    };
    $morning = null;
    $evening = null;
    foreach ($createFirst === 'evening' ? ['evening', 'morning'] : ['morning', 'evening'] as $which) {
        if ($which === 'morning') {
            $morning = $make('08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10);
        } else {
            $evening = $make('18:00', '22:00', PlannedShiftStatus::Draft, 'Avond', 15);
        }
    }

    multiSaveGrid($tenant, $admin, $worker, $week, $raw);

    $kept = PlannedShift::query()->where('start_time', $keptStart)->first();
    $replaced = PlannedShift::query()->where('start_time', '12:00')->first();

    expect($kept)->not->toBeNull()
        ->and((int) $kept->id)->toBe($keptStart === '08:00' ? (int) $morning->id : (int) $evening->id)
        ->and($kept->end_time)->toBe($keptStart === '08:00' ? '12:00' : '22:00')
        ->and($kept->description)->toBe($keptNote)
        ->and($kept->break_minutes)->toBe($keptBreak)
        ->and($kept->status)->toBe($keptStatus)
        ->and((int) $kept->shift_type_id)->toBe((int) $type->id)
        ->and((int) $kept->unit_id)->toBe((int) $unit->id)
        ->and(PlannedShift::find($keptStart === '08:00' ? $evening->id : $morning->id))->toBeNull()
        ->and($replaced)->not->toBeNull()
        ->and($replaced->status)->toBe(PlannedShiftStatus::Draft)
        ->and($replaced->shift_type_id)->toBeNull()
        ->and(PlannedShift::query()->count())->toBe(2);
})->with([
    'vrije tijd eerst, ochtend eerst' => ['12:00-16:00 · V/G1', 'morning', '08:00', 'Ochtend', 10, PlannedShiftStatus::Published],
    'vrije tijd eerst, avond eerst' => ['12:00-16:00 · V/G1', 'evening', '08:00', 'Ochtend', 10, PlannedShiftStatus::Published],
    'code eerst, ochtend eerst' => ['V/G1 · 12:00-16:00', 'morning', '08:00', 'Ochtend', 10, PlannedShiftStatus::Published],
    'code eerst, avond eerst' => ['V/G1 · 12:00-16:00', 'evening', '08:00', 'Ochtend', 10, PlannedShiftStatus::Published],
]);

it('behoudt bij twee dezelfde codes de aangepaste tijden, notities, pauzes en statussen', function (string $createFirst) {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
    ]);
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $make = function (string $start, string $end, PlannedShiftStatus $status, string $note, int $break) use ($tenant, $worker, $week, $type, $unit, $location): PlannedShift {
        $row = plannedBlock($tenant, $worker, $week, $start, $end, $status);
        $row->update([
            'shift_type_id' => $type->id,
            'unit_id' => $unit->id,
            'unit_code' => 'G1',
            'unit_name' => 'Groep 1',
            'location_id' => $location->id,
            'description' => $note,
            'break_minutes' => $break,
        ]);

        return $row;
    };
    if ($createFirst === 'evening') {
        $evening = $make('18:00', '22:00', PlannedShiftStatus::Draft, 'Avond', 15);
        $morning = $make('08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10);
    } else {
        $morning = $make('08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10);
        $evening = $make('18:00', '22:00', PlannedShiftStatus::Draft, 'Avond', 15);
    }

    multiSaveGrid($tenant, $admin, $worker, $week, 'V/G1 · V/G1');

    $keptMorning = PlannedShift::find($morning->id);
    $keptEvening = PlannedShift::find($evening->id);

    expect($keptMorning)->not->toBeNull()
        ->and($keptMorning->start_time)->toBe('08:00')
        ->and($keptMorning->end_time)->toBe('12:00')
        ->and($keptMorning->description)->toBe('Ochtend')
        ->and($keptMorning->break_minutes)->toBe(10)
        ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
        ->and($keptEvening)->not->toBeNull()
        ->and($keptEvening->start_time)->toBe('18:00')
        ->and($keptEvening->end_time)->toBe('22:00')
        ->and($keptEvening->description)->toBe('Avond')
        ->and($keptEvening->break_minutes)->toBe(15)
        ->and($keptEvening->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::query()->count())->toBe(2);
})->with([
    'ochtend eerst' => 'morning',
    'avond eerst' => 'evening',
]);

it('voegt een blok vóór bestaande blokken toe zonder hun identiteit te vervangen', function () {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $morning = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10, true);
    $evening = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '18:00', '20:00', PlannedShiftStatus::Published, 'Avond', 15, false);

    multiSaveGrid($tenant, $admin, $worker, $week, '06:00-07:00 · V/G1 · 1800-2000');

    $keptMorning = PlannedShift::find($morning->id);
    $keptEvening = PlannedShift::find($evening->id);
    $added = PlannedShift::query()->where('start_time', '06:00')->first();

    expect($keptMorning)->not->toBeNull()
        ->and($keptMorning->end_time)->toBe('12:00')
        ->and($keptMorning->description)->toBe('Ochtend')
        ->and($keptMorning->break_minutes)->toBe(10)
        ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
        ->and((int) $keptMorning->shift_type_id)->toBe((int) $type->id)
        ->and($keptEvening)->not->toBeNull()
        ->and($keptEvening->end_time)->toBe('20:00')
        ->and($keptEvening->description)->toBe('Avond')
        ->and($keptEvening->break_minutes)->toBe(15)
        ->and($keptEvening->status)->toBe(PlannedShiftStatus::Published)
        ->and($added)->not->toBeNull()
        ->and($added->end_time)->toBe('07:00')
        ->and($added->description)->toBeNull()
        ->and($added->break_minutes)->toBe(0)
        ->and($added->status)->toBe(PlannedShiftStatus::Draft)
        ->and($added->shift_type_id)->toBeNull()
        ->and(PlannedShift::query()->count())->toBe(3);
});

it('verwijdert het eerste, middelste of laatste blok en houdt de overige rijen', function (string $which) {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $morning = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '08:00', '10:00', PlannedShiftStatus::Published, 'Ochtend', 10, true);
    $middle = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '12:00', '14:00', PlannedShiftStatus::Published, 'Middag', 5, false);
    $evening = multiAlignmentBlock(
        $tenant,
        $worker,
        $week,
        $type,
        $unit,
        $location,
        '18:00',
        $which === 'middle' ? '22:00' : '20:00',
        $which === 'middle' ? PlannedShiftStatus::Draft : PlannedShiftStatus::Published,
        'Avond',
        15,
        $which === 'middle',
    );
    $raw = match ($which) {
        'first' => '1200-1400 · 1800-2000',
        'middle' => 'V/G1 · V/G1',
        'last' => 'V/G1 · 1200-1400',
    };

    multiSaveGrid($tenant, $admin, $worker, $week, $raw);

    $keptMorning = PlannedShift::find($morning->id);
    $keptMiddle = PlannedShift::find($middle->id);
    $keptEvening = PlannedShift::find($evening->id);

    if ($which === 'first') {
        expect($keptMorning)->toBeNull()
            ->and($keptMiddle)->not->toBeNull()
            ->and($keptMiddle->description)->toBe('Middag')
            ->and($keptMiddle->break_minutes)->toBe(5)
            ->and($keptMiddle->status)->toBe(PlannedShiftStatus::Published)
            ->and($keptMiddle->start_time)->toBe('12:00')
            ->and($keptEvening)->not->toBeNull()
            ->and($keptEvening->description)->toBe('Avond')
            ->and($keptEvening->break_minutes)->toBe(15)
            ->and($keptEvening->status)->toBe(PlannedShiftStatus::Published)
            ->and(PlannedShift::query()->count())->toBe(2);
    } elseif ($which === 'middle') {
        expect($keptMiddle)->toBeNull()
            ->and($keptMorning)->not->toBeNull()
            ->and($keptMorning->start_time)->toBe('08:00')
            ->and($keptMorning->end_time)->toBe('10:00')
            ->and($keptMorning->description)->toBe('Ochtend')
            ->and($keptMorning->break_minutes)->toBe(10)
            ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
            ->and($keptEvening)->not->toBeNull()
            ->and($keptEvening->start_time)->toBe('18:00')
            ->and($keptEvening->end_time)->toBe('22:00')
            ->and($keptEvening->description)->toBe('Avond')
            ->and($keptEvening->break_minutes)->toBe(15)
            ->and($keptEvening->status)->toBe(PlannedShiftStatus::Draft)
            ->and(PlannedShift::query()->count())->toBe(2);
    } else {
        expect($keptEvening)->toBeNull()
            ->and($keptMorning)->not->toBeNull()
            ->and($keptMorning->description)->toBe('Ochtend')
            ->and($keptMorning->break_minutes)->toBe(10)
            ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
            ->and($keptMiddle)->not->toBeNull()
            ->and($keptMiddle->description)->toBe('Middag')
            ->and($keptMiddle->break_minutes)->toBe(5)
            ->and($keptMiddle->status)->toBe(PlannedShiftStatus::Published)
            ->and(PlannedShift::query()->count())->toBe(2);
    }
})->with(['first', 'middle', 'last']);

it('houdt twee unieke blokken bij een omgekeerde tekstvolgorde', function () {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $morning = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10, true);
    $evening = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '18:00', '20:00', PlannedShiftStatus::Published, 'Avond', 15, false);

    multiSaveGrid($tenant, $admin, $worker, $week, '1800-2000 · V/G1');

    $keptMorning = PlannedShift::find($morning->id);
    $keptEvening = PlannedShift::find($evening->id);

    expect($keptMorning)->not->toBeNull()
        ->and($keptMorning->start_time)->toBe('08:00')
        ->and($keptMorning->end_time)->toBe('12:00')
        ->and($keptMorning->description)->toBe('Ochtend')
        ->and($keptMorning->break_minutes)->toBe(10)
        ->and($keptMorning->status)->toBe(PlannedShiftStatus::Published)
        ->and((int) $keptMorning->shift_type_id)->toBe((int) $type->id)
        ->and($keptEvening)->not->toBeNull()
        ->and($keptEvening->start_time)->toBe('18:00')
        ->and($keptEvening->end_time)->toBe('20:00')
        ->and($keptEvening->description)->toBe('Avond')
        ->and($keptEvening->break_minutes)->toBe(15)
        ->and($keptEvening->status)->toBe(PlannedShiftStatus::Published)
        ->and($keptEvening->shift_type_id)->toBeNull()
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('zet een uniek middagblok vast tussen twee dezelfde codes', function () {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $morning = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '08:00', '10:00', PlannedShiftStatus::Published, 'Ochtend', 10, true);
    $middle = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '12:00', '14:00', PlannedShiftStatus::Published, 'Middag', 5, false);
    $evening = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '18:00', '22:00', PlannedShiftStatus::Draft, 'Avond', 15, true);

    multiSaveGrid($tenant, $admin, $worker, $week, '1200-1400 · V/G1 · V/G1');

    expect(PlannedShift::find($morning->id)?->description)->toBe('Ochtend')
        ->and(PlannedShift::find($morning->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::find($morning->id)?->start_time)->toBe('08:00')
        ->and(PlannedShift::find($middle->id)?->description)->toBe('Middag')
        ->and(PlannedShift::find($middle->id)?->break_minutes)->toBe(5)
        ->and(PlannedShift::find($middle->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::find($evening->id)?->description)->toBe('Avond')
        ->and(PlannedShift::find($evening->id)?->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::find($evening->id)?->start_time)->toBe('18:00')
        ->and(PlannedShift::query()->count())->toBe(3);
});

it('vervangt een vrije tijd met andere tijden en houdt het buurblok', function () {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $midday = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '12:00', '14:00', PlannedShiftStatus::Published, 'Middag', 5, false);
    $evening = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '18:00', '20:00', PlannedShiftStatus::Published, 'Avond', 15, false);

    multiSaveGrid($tenant, $admin, $worker, $week, '1300-1500 · 1800-2000');

    $replacement = PlannedShift::query()->where('start_time', '13:00')->first();

    expect(PlannedShift::find($midday->id))->toBeNull()
        ->and($replacement)->not->toBeNull()
        ->and($replacement->end_time)->toBe('15:00')
        ->and($replacement->description)->toBeNull()
        ->and($replacement->break_minutes)->toBe(0)
        ->and($replacement->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::find($evening->id)?->description)->toBe('Avond')
        ->and(PlannedShift::find($evening->id)?->break_minutes)->toBe(15)
        ->and(PlannedShift::find($evening->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('bewaart een ongewijzigde cel met twee dezelfde codes via de API en via keep', function () {
    [$tenant, $admin, $worker, $week, $location, $unit, $type] = multiAlignmentFixture();
    $morning = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '08:00', '12:00', PlannedShiftStatus::Published, 'Ochtend', 10, true);
    $evening = multiAlignmentBlock($tenant, $worker, $week, $type, $unit, $location, '18:00', '22:00', PlannedShiftStatus::Draft, 'Avond', 15, true);
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/time/schedule', [
            'week_start' => $week,
            'worker_ids' => [$worker->id],
            'cells' => multiWeekCells($worker, $week, [$week => 'V/G1 · V/G1']),
        ])
        ->assertOk();

    Tenancy::actAs($tenant->id);
    expect(PlannedShift::find($morning->id)?->description)->toBe('Ochtend')
        ->and(PlannedShift::find($morning->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::find($evening->id)?->description)->toBe('Avond')
        ->and(PlannedShift::find($evening->id)?->break_minutes)->toBe(15)
        ->and(PlannedShift::find($evening->id)?->status)->toBe(PlannedShiftStatus::Draft);

    $cells = array_slice(multiWeekCells($worker, $week), 0, 5);
    $cells[0]['keep'] = true;
    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('teamFilter', (string) $worker->internal_team_id)
        ->set('weekStart', $week)
        ->call('save', $cells)
        ->assertNotDispatched('roster-save-failed');

    Tenancy::actAs($tenant->id);
    expect(PlannedShift::find($morning->id)?->description)->toBe('Ochtend')
        ->and(PlannedShift::find($evening->id)?->start_time)->toBe('18:00')
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('houdt alle rijen bij een ongeldige cel', function () {
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $morning = plannedBlock($tenant, $worker, $week, '07:00', '10:00');
    $morning->update(['description' => 'Ochtend', 'break_minutes' => 10]);
    $evening = plannedBlock($tenant, $worker, $week, '18:00', '20:00');
    $evening->update(['description' => 'Avond', 'break_minutes' => 15]);

    expect(fn () => multiSaveGrid($tenant, $admin, $worker, $week, 'ZZZ · 1800-2000'))
        ->toThrow(RosterValidationException::class);

    expect(PlannedShift::find($morning->id)?->description)->toBe('Ochtend')
        ->and(PlannedShift::find($morning->id)?->break_minutes)->toBe(10)
        ->and(PlannedShift::find($morning->id)?->status)->toBe(PlannedShiftStatus::Published)
        ->and(PlannedShift::find($evening->id)?->description)->toBe('Avond')
        ->and(PlannedShift::find($evening->id)?->break_minutes)->toBe(15)
        ->and(PlannedShift::query()->count())->toBe(2);
});

/**
 * @return array{0: Tenant, 1: User, 2: Worker, 3: string, 4: Location, 5: Unit, 6: ShiftType}
 */
function multiAlignmentFixture(): array
{
    [$tenant, $admin, $worker] = multiBlockTenant();
    $week = multiNextWeek();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
    ]);
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );

    return [$tenant, $admin, $worker, $week, $location, $unit, $type];
}

function multiAlignmentBlock(
    Tenant $tenant,
    Worker $worker,
    string $week,
    ShiftType $type,
    Unit $unit,
    Location $location,
    string $start,
    string $end,
    PlannedShiftStatus $status,
    string $note,
    int $break,
    bool $coded,
): PlannedShift {
    $row = plannedBlock($tenant, $worker, $week, $start, $end, $status);
    $row->update([
        'shift_type_id' => $coded ? $type->id : null,
        'unit_id' => $coded ? $unit->id : null,
        'unit_code' => $coded ? 'G1' : null,
        'unit_name' => $coded ? 'Groep 1' : null,
        'location_id' => $coded ? $location->id : null,
        'description' => $note,
        'break_minutes' => $break,
    ]);

    return $row->fresh();
}
