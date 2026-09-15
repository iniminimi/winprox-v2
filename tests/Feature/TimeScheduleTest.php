<?php

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
use App\Exceptions\RosterValidationException;
use App\Livewire\Time\RosterIndex;
use App\Livewire\Time\ShiftTypesIndex;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Worker;
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
        ->assertSee('data-wp-roster-legend', false)
        ->assertDontSee('id="schedule-type"', false);

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
        ->assertSee('wp-roster-month', false);

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
