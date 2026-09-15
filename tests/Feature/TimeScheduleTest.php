<?php

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\ListWorkerNotificationsAction;
use App\Actions\Notifications\MarkWorkerNotificationsReadAction;
use App\Actions\Time\AssertPlannedShiftNoOverlapAction;
use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ParseRosterCellAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Exceptions\RosterValidationException;
use App\Livewire\Time\RosterIndex;
use App\Livewire\Time\ShiftTypesIndex;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function scheduleTenant(): array
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

function scheduleWeekStart(): string
{
    return Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
}

function scheduleCells(array $workers, string $weekStart, array $values = []): array
{
    $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
    $cells = [];
    foreach ($workers as $worker) {
        for ($i = 0; $i < 7; $i++) {
            $date = $monday->copy()->addDays($i)->toDateString();
            $cells[] = [
                'worker_id' => $worker->id,
                'date' => $date,
                'raw' => $values[$worker->id.':'.$date] ?? '',
            ];
        }
    }

    return $cells;
}

function scheduleWeekdayCells(array $workers, string $weekStart, array $values = []): array
{
    return array_values(array_filter(
        scheduleCells($workers, $weekStart, $values),
        fn (array $cell) => ! Carbon::parse($cell['date'])->isWeekend(),
    ));
}

it('parses codes, free times and rejects night shifts', function () {
    [$tenant] = scheduleTenant();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('v', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        null,
    );

    $parse = app(ParseRosterCellAction::class);
    $types = ShiftType::query()->get();

    expect($parse->handle('', $types)->kind)->toBe(RosterCellKind::Empty)
        ->and($parse->handle('v', $types)->kind)->toBe(RosterCellKind::ShiftType)
        ->and($parse->handle('v', $types)->code)->toBe('V')
        ->and($parse->handle('v', $types)->startTime)->toBe('07:00')
        ->and($parse->handle('07:00-15:00', $types)->kind)->toBe(RosterCellKind::FreeTime)
        ->and($parse->handle('07:00-15:00', $types)->breakMinutes)->toBe(0)
        ->and($parse->handle('22:00-06:00', $types)->kind)->toBe(RosterCellKind::Invalid)
        ->and($parse->handle('22:00-06:00', $types)->errorKey)->toBe('time.schedule.errors.night_not_allowed')
        ->and($parse->handle('X', $types)->kind)->toBe(RosterCellKind::Invalid)
        ->and($type->code)->toBe('V')
        ->and($type->color)->toBe(ShiftTypeColor::Emerald);

    $neutral = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('N', 'Neutraal', '08:00', '12:00', 0, ShiftTypeColor::None),
        null,
    );
    expect($neutral->color)->toBe(ShiftTypeColor::None)
        ->and($neutral->color->hasFill())->toBeFalse();
});

it('telt raakvlak-tijden niet als overlap', function () {
    $assert = app(AssertPlannedShiftNoOverlapAction::class);

    $assert->handle([
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '07:00', 'end' => '11:00'],
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '11:00', 'end' => '15:00'],
    ]);

    expect(true)->toBeTrue();
});

it('weigert een minuut overlap en identieke vensters', function () {
    $assert = app(AssertPlannedShiftNoOverlapAction::class);

    expect(fn () => $assert->handle([
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '07:00', 'end' => '11:00'],
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '10:59', 'end' => '15:00'],
    ]))->toThrow(RosterValidationException::class);

    expect(fn () => $assert->handle([
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '07:00', 'end' => '15:00'],
        ['worker_id' => 1, 'date' => '2026-09-14', 'start' => '07:00', 'end' => '15:00'],
    ]))->toThrow(RosterValidationException::class);
});

it('slaat een week op en houdt published na correctie', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        $admin->id,
    );
    $week = scheduleWeekStart();
    $monday = $week;

    $save = app(SavePlannedShiftsAction::class);
    $save->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$monday => 'V',
        ])),
        $admin->id,
    );

    $shift = PlannedShift::query()->first();
    expect($shift->status)->toBe(PlannedShiftStatus::Draft)
        ->and($shift->start_time)->toBe('07:00')
        ->and($shift->break_minutes)->toBe(30);

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );

    $save->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$monday => '08:00-16:00',
        ])),
        $admin->id,
    );

    $updated = PlannedShift::query()->first();
    expect($updated->status)->toBe(PlannedShiftStatus::Published)
        ->and($updated->shift_type_id)->toBeNull()
        ->and($updated->start_time)->toBe('08:00');
});

it('toont een inactieve code als vrije tijd zodat opslaan blijft werken', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        $admin->id,
    );
    $week = scheduleWeekStart();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'V',
        ])),
        $admin->id,
    );

    app(\App\Actions\Time\SetShiftTypeActiveAction::class)->handle($type, false, $admin->id);

    $shift = PlannedShift::query()->first();
    expect($shift->fresh()->load('shiftType')->displayValue())->toBe('07:00-15:00');
});

it('weigert copy naar een week die al shiften heeft', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    $next = Carbon::parse($week)->addWeek()->toDateString();
    $save = app(SavePlannedShiftsAction::class);
    $save->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => '07:00-15:00',
        ])),
        $admin->id,
    );
    $save->handle(
        $tenant,
        new SavePlannedShiftsData($next, [$worker->id], scheduleCells([$worker], $next, [
            $worker->id.':'.$next => '07:00-15:00',
        ])),
        $admin->id,
    );

    expect(fn () => app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData($week, $next, [$worker->id]),
        $admin->id,
    ))->toThrow(RosterValidationException::class);
});

it('kopieert een lege doelweek als draft', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    $next = Carbon::parse($week)->addWeek()->toDateString();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => '07:00-15:00',
        ])),
        $admin->id,
    );

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );

    $copied = app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData($week, $next, [$worker->id]),
        $admin->id,
    );

    expect($copied)->toHaveCount(1)
        ->and($copied[0]->status)->toBe(PlannedShiftStatus::Draft)
        ->and($copied[0]->work_date->toDateString())->toBe($next);
});

it('opent het uurrooster voor een admin', function () {
    [$tenant, $admin] = scheduleTenant();

    $this->actingAs($admin)
        ->get(route('time.schedule.index'))
        ->assertOk()
        ->assertSee('wp-roster-sheet', false)
        ->assertSee(__('time.schedule.legend_button'), false)
        ->assertSee(__('time.schedule.nav_prev'), false)
        ->assertSee(__('time.schedule.week_current', ['number' => now()->startOfWeek(Carbon::MONDAY)->isoWeek()]), false)
        ->assertSee(__('time.schedule.weekends'), false)
        ->assertSee('id="schedule-weekends"', false)
        ->assertSee('id="schedule-location"', false)
        ->assertDontSee('id="schedule-type"', false)
        ->assertDontSee('<label class="wp-filter-inline-label" for="schedule-team">', false);

    $this->actingAs($admin)
        ->get(route('time.shift-types.index'))
        ->assertOk()
        ->assertSee(__('time.shift_types.title'), false);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->assertOk();

    Livewire::actingAs($admin)
        ->test(ShiftTypesIndex::class)
        ->call('openCreate')
        ->set('typeCode', 'VM')
        ->set('typeLabel', 'Voormiddag')
        ->set('typeStart', '07:00')
        ->set('typeEnd', '15:00')
        ->set('typeBreak', 30)
        ->set('typeColor', 'emerald')
        ->call('save')
        ->assertHasNoErrors();

    expect(ShiftType::query()->where('tenant_id', $tenant->id)->where('code', 'VM')->exists())->toBeTrue();
});

it('slaat een week op via de API', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/time/schedule', [
            'week_start' => $week,
            'worker_ids' => [$worker->id],
            'cells' => scheduleCells([$worker], $week, [
                $worker->id.':'.$week => '07:00-12:00',
            ]),
        ])
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    Tenancy::actAs($tenant->id);
    expect(PlannedShift::query()->count())->toBe(1);
});

it('toont een maandoverzicht met dagnummers', function () {
    [$tenant, $admin] = scheduleTenant();

    $this->actingAs($admin)
        ->get(route('time.schedule.index', ['view' => 'month', 'week' => '2026-09-01']))
        ->assertOk()
        ->assertSee(__('time.schedule.view_month'), false)
        ->assertSee('wp-roster-page--month', false)
        ->assertSee('wp-roster-month', false)
        ->assertSee(__('time.schedule.nav_next'), false)
        ->assertDontSee('id="schedule-weekends"', false);

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-15',
        null,
        $admin,
        'month',
    );

    expect($snapshot->dates)->toHaveCount(30)
        ->and($snapshot->period)->toBe('month')
        ->and($snapshot->dayNumbers[0])->toBe(1)
        ->and($snapshot->dayNumbers[29])->toBe(30)
        ->and($snapshot->monthLabel)->toContain('2026');
});

it('toont geen weekendkolommen als weekends uit staan', function () {
    [$tenant, $admin] = scheduleTenant();
    $week = scheduleWeekStart();

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        $week,
        null,
        $admin,
        'week',
        false,
    );

    expect($snapshot->dates)->toHaveCount(5)
        ->and($snapshot->dates)->not->toContain(Carbon::parse($week)->addDays(5)->toDateString())
        ->and($snapshot->dates)->not->toContain(Carbon::parse($week)->addDays(6)->toDateString());

    $this->actingAs($admin)
        ->get(route('time.schedule.index', ['week' => $week, 'weekends' => 0]))
        ->assertOk()
        ->assertSee('id="schedule-weekends"', false);
});

it('laat weekenddiensten staan als weekends uit staan bij opslaan', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    $saturday = Carbon::parse($week)->addDays(5)->toDateString();

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$saturday => '07:00-12:00',
        ])),
        $admin->id,
    );

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleWeekdayCells([$worker], $week, [
            $worker->id.':'.$week => '08:00-16:00',
        ]), 'week', false),
        $admin->id,
    );

    Tenancy::actAs($tenant->id);
    $monday = PlannedShift::query()->whereDate('work_date', $week)->first();
    $weekend = PlannedShift::query()->whereDate('work_date', $saturday)->first();

    expect($monday)->not->toBeNull()
        ->and($monday->start_time)->toBe('08:00')
        ->and($weekend)->not->toBeNull()
        ->and($weekend->start_time)->toBe('07:00');
});

it('slaat een maandgrid op', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $cells = [];
    for ($day = 1; $day <= 30; $day++) {
        $date = sprintf('2026-09-%02d', $day);
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $date === '2026-09-14' ? '07:00-15:00' : '',
        ];
    }

    $saved = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-09-01', [$worker->id], $cells, 'month'),
        $admin->id,
    );

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->work_date->toDateString())->toBe('2026-09-14');
});

it('bevat schedule-webhook-events', function () {
    expect(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.saved')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.copied')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.published')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.shift_type.saved');
});

it('slaat verlof op zonder uren en weigert overlap met werk', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $leave = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    expect($leave->kind)->toBe(ShiftTypeKind::Leave)
        ->and($leave->start_time)->toBeNull();

    $parse = app(ParseRosterCellAction::class);
    $parsed = $parse->handle('VL', ShiftType::query()->get());
    expect($parsed->kind)->toBe(RosterCellKind::Absence)
        ->and($parsed->startTime)->toBeNull();

    $week = scheduleWeekStart();
    $saved = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'VL',
        ])),
        $admin->id,
    );

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->kind)->toBe(ShiftTypeKind::Leave)
        ->and($saved[0]->start_time)->toBeNull()
        ->and($saved[0]->break_minutes)->toBe(0);

    $assert = app(AssertPlannedShiftNoOverlapAction::class);
    expect(fn () => $assert->handle([
        ['worker_id' => 1, 'date' => $week, 'start' => '07:00', 'end' => '15:00', 'kind' => 'work'],
        ['worker_id' => 1, 'date' => $week, 'start' => null, 'end' => null, 'kind' => 'leave'],
    ]))->toThrow(RosterValidationException::class);
});

it('maakt bij publiceren één notificatie per uitvoerder en reset read_at bij republish', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => '07:00-15:00',
        ])),
        $admin->id,
    );

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );

    $row = WorkerNotification::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->type)->toBe(WorkerNotificationType::RosterPublished)
        ->and($row->reference_id)->toBe($week)
        ->and($row->read_at)->toBeNull();

    $items = app(ListWorkerNotificationsAction::class)->handle(
        $worker,
        (int) $tenant->id,
        WorkerNotificationType::RosterPublished,
    );
    expect($items)->toHaveCount(1)
        ->and($items[0]->target->screen)->toBe('schedule')
        ->and($items[0]->target->cursor)->toBe($week);

    app(MarkWorkerNotificationsReadAction::class)->handle(
        $worker,
        (int) $tenant->id,
        WorkerNotificationType::RosterPublished,
    );
    expect(WorkerNotification::query()->first()->read_at)->not->toBeNull();

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );
    expect(WorkerNotification::query()->count())->toBe(1)
        ->and(WorkerNotification::query()->first()->read_at)->toBeNull();
});

it('weigert een planned_shift-id als roster_published reference_id', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    expect(fn () => app(CreateNotificationAction::class)->handle(
        $tenant,
        $worker,
        WorkerNotificationType::RosterPublished,
        '42',
    ))->toThrow(InvalidArgumentException::class);
});

it('markeert gepland vs geklokt op een verleden published dag', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => '07:00-15:00',
        ])),
        $admin->id,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        $week,
        null,
        $admin,
        'week',
    );

    expect($snapshot->attendance[$worker->id.':'.$week] ?? null)->toBe('missing');
});

it('kopieert afwezigheid als draft naar de volgende week', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    $week = scheduleWeekStart();
    $next = Carbon::parse($week)->addWeek()->toDateString();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'VL',
        ])),
        $admin->id,
    );

    $copied = app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData($week, $next, [$worker->id]),
        $admin->id,
    );

    expect($copied)->toHaveCount(1)
        ->and($copied[0]->kind)->toBe(ShiftTypeKind::Leave)
        ->and($copied[0]->start_time)->toBeNull()
        ->and($copied[0]->status)->toBe(PlannedShiftStatus::Draft);
});

it('parses a unit code in the same cell and snapshots place on save', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Site Noord']);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
        'is_active' => true,
    ]);
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        $admin->id,
    );
    $types = ShiftType::query()->get();
    $units = Unit::query()->get();
    $parse = app(ParseRosterCellAction::class);

    $slash = $parse->handle('D1/G1', $types, $units);
    $space = $parse->handle('D1 G1', $types, $units);
    $time = $parse->handle('07:00-12:00/g1', $types, $units);

    expect($slash->kind)->toBe(RosterCellKind::ShiftType)
        ->and($slash->unitId)->toBe((int) $unit->id)
        ->and($slash->unitCode)->toBe('G1')
        ->and($slash->unitName)->toBe('Groep 1')
        ->and($slash->locationId)->toBe((int) $location->id)
        ->and($space->unitCode)->toBe('G1')
        ->and($time->kind)->toBe(RosterCellKind::FreeTime)
        ->and($time->unitCode)->toBe('G1');

    $week = scheduleWeekStart();
    $saved = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'D1/G1',
        ])),
        $admin->id,
    );

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->unit_id)->toBe($unit->id)
        ->and($saved[0]->unit_code)->toBe('G1')
        ->and($saved[0]->unit_name)->toBe('Groep 1')
        ->and($saved[0]->location_id)->toBe($location->id)
        ->and($saved[0]->displayValue())->toBe('D1/G1');

    $next = Carbon::parse($week)->addWeek()->toDateString();
    $copied = app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData($week, $next, [$worker->id]),
        $admin->id,
    );
    expect($copied[0]->unit_code)->toBe('G1')
        ->and($copied[0]->unit_name)->toBe('Groep 1');
});

it('weigert afwezigheid met groep en dubbele groepscode zonder locatiefilter', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $siteA = Location::factory()->create(['tenant_id' => $tenant->id]);
    $siteB = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $siteA->id,
        'name' => 'Groep 1 A',
        'roster_code' => 'G1',
    ]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $siteB->id,
        'name' => 'Groep 1 B',
        'roster_code' => 'G1',
    ]);
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 0, ShiftTypeColor::Emerald),
        $admin->id,
    );
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    $types = ShiftType::query()->get();
    $units = Unit::query()->get();
    $parse = app(ParseRosterCellAction::class);

    expect($parse->handle('VL/G1', $types, $units)->errorKey)->toBe('time.schedule.errors.absence_has_unit')
        ->and($parse->handle('D1/G1', $types, $units)->errorKey)->toBe('time.schedule.errors.ambiguous_unit')
        ->and($parse->handle('D1/G1', $types, $units)->ambiguousCount)->toBe(2)
        ->and($parse->handle('D1/XX', $types, $units)->errorKey)->toBe('time.schedule.errors.unknown_unit')
        ->and($parse->handle('D1/G1', $types, $units->where('location_id', $siteA->id))->unitId)->not->toBeNull();

    $week = scheduleWeekStart();
    expect(fn () => app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'D1/G1',
        ])),
        $admin->id,
    ))->toThrow(RosterValidationException::class);

    $saved = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'D1/G1',
        ]), 'week', true, (int) $siteA->id),
        $admin->id,
        null,
    );
    expect($saved[0]->location_id)->toBe($siteA->id);
});

it('negeert een groepscode op een locatie waar de uitvoerder niet mag prikken', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $home = Location::factory()->create(['tenant_id' => $tenant->id]);
    $other = Location::factory()->create(['tenant_id' => $tenant->id]);
    $worker->locations()->sync([$home->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $other->id,
        'name' => 'Babyland',
        'roster_code' => 'G1',
    ]);
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 0, ShiftTypeColor::Emerald),
        $admin->id,
    );

    $week = scheduleWeekStart();
    try {
        app(SavePlannedShiftsAction::class)->handle(
            $tenant,
            new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
                $worker->id.':'.$week => 'D1/G1',
            ])),
            $admin->id,
        );
        expect(false)->toBeTrue();
    } catch (RosterValidationException $e) {
        expect($e->cells[0]['error'] ?? null)->toBe('time.schedule.errors.unknown_unit');
    }
});

it('filtert uitvoerders op locatie in het uurrooster', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $siteA = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Noord']);
    $siteB = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zuid']);
    $home = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $away = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $home->locations()->sync([$siteA->id]);
    $away->locations()->sync([$siteB->id]);

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        (int) $siteA->id,
    );

    $ids = array_map(fn ($row) => (int) $row['id'], $snapshot->workers);
    expect($ids)->toContain((int) $home->id)
        ->and($ids)->toContain((int) $worker->id)
        ->and($ids)->not->toContain((int) $away->id)
        ->and($snapshot->locations)->not->toBeEmpty()
        ->and($snapshot->units)->toBeArray();
});

