<?php

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\ListWorkerNotificationsAction;
use App\Actions\Notifications\MarkWorkerNotificationsReadAction;
use App\Actions\Time\AssertPlannedShiftNoOverlapAction;
use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\CorrectPlannedShiftAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ParseRosterCellAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ReplacePlannedShiftAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SetShiftTypeActiveAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedDayData;
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
use App\Models\AuditLog;
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
use App\Models\WorkerWeekdayMinute;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\RosterHistoryLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
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
        new SaveShiftTypeData('v', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
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
        ->and($type->color)->toBe(ShiftTypeColor::Green);

    $neutral = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('N', 'Neutraal', '08:00', '12:00', 0, ShiftTypeColor::None),
        null,
    );
    expect($neutral->color)->toBe(ShiftTypeColor::None)
        ->and($neutral->color->hasFill())->toBeFalse();
});

it('zet oude shifttype-kleuren om naar het palet', function (string $legacy, ShiftTypeColor $expected) {
    [$tenant] = scheduleTenant();
    $type = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'color' => ShiftTypeColor::Green,
    ]);
    DB::table('shift_types')->where('id', $type->id)->update(['color' => $legacy]);

    (new RemapShiftTypeColors)->up();
    (new SoftenShiftTypeColors)->up();

    expect($type->fresh()->color)->toBe($expected);
})->with([
    'emerald' => ['emerald', ShiftTypeColor::Green],
    'mint' => ['mint', ShiftTypeColor::Green],
    'teal' => ['teal', ShiftTypeColor::Petrol],
    'sky' => ['sky', ShiftTypeColor::Blue],
    'ice' => ['ice', ShiftTypeColor::Blue],
    'navy' => ['navy', ShiftTypeColor::Blue],
    'amber' => ['amber', ShiftTypeColor::Amber],
    'gold' => ['gold', ShiftTypeColor::Amber],
    'sand' => ['sand', ShiftTypeColor::Amber],
    'orange' => ['orange', ShiftTypeColor::Amber],
    'yellow' => ['yellow', ShiftTypeColor::Amber],
    'rose' => ['rose', ShiftTypeColor::Pink],
    'slate' => ['slate', ShiftTypeColor::Gray],
]);

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

it('zet een correctie op een gepubliceerde week terug op draft tot herpublicatie', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
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
    expect($updated->status)->toBe(PlannedShiftStatus::Draft)
        ->and($updated->shift_type_id)->toBeNull()
        ->and($updated->start_time)->toBe('08:00')
        ->and(WorkerNotification::query()
            ->where('type', WorkerNotificationType::RosterChanged->value)
            ->count())->toBe(0);

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        $admin->id,
    );

    expect(PlannedShift::query()->first()->status)->toBe(PlannedShiftStatus::Published);
});

it('toont een inactieve code als vrije tijd zodat opslaan blijft werken', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
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

    app(SetShiftTypeActiveAction::class)->handle($type, false, $admin->id);

    $shift = PlannedShift::query()->first();
    expect($shift->fresh()->load('shiftType')->displayValue())->toBe('07:00-15:00');
});

it('weigert copy naar een week die al shiften heeft', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
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
    $this->travelTo(Carbon::parse('2026-09-13'));
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
        ->assertSee(__('time.schedule.prev_week'), false)
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
        ->test(RosterIndex::class)
        ->call('openUnsavedLeaveModal')
        ->assertSee(__('time.schedule.unsaved.title'), false)
        ->assertSee(__('time.schedule.unsaved.body'), false)
        ->call('closeUnsavedLeaveModal')
        ->assertDontSee(__('time.schedule.unsaved.stay'), false);

    Livewire::actingAs($admin)
        ->test(ShiftTypesIndex::class)
        ->call('openCreate')
        ->assertSee(__('time.schedule.types.colors.purple'), false)
        ->assertSee(__('time.schedule.types.colors.amber'), false)
        ->set('typeCode', 'VM')
        ->set('typeLabel', 'Voormiddag')
        ->set('typeStart', '07:00')
        ->set('typeEnd', '15:00')
        ->set('typeBreak', 30)
        ->set('typeColor', 'green')
        ->call('save')
        ->assertHasNoErrors();

    expect(ShiftType::query()->where('tenant_id', $tenant->id)->where('code', 'VM')->exists())->toBeTrue();
});

it('toont Niet gepubliceerd zolang de week draft-shifts bevat', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => now()->toDateString(),
        'status' => PlannedShiftStatus::Draft,
    ]);

    Livewire::actingAs($admin)
        ->withQueryParams(['team' => (string) $team->id])
        ->test(RosterIndex::class)
        ->assertSeeHtml('>'.e(__('time.schedule.not_published')).'</span>');

    $shift->update(['status' => PlannedShiftStatus::Published]);

    Livewire::actingAs($admin)
        ->withQueryParams(['team' => (string) $team->id])
        ->test(RosterIndex::class)
        ->assertDontSeeHtml('>'.e(__('time.schedule.not_published')).'</span>');
});

it('vraagt eerst een team- of locatiekeuze voor het rooster', function () {
    [$tenant, $admin, $team] = scheduleTenant();

    $this->actingAs($admin)
        ->get(route('time.schedule.index'))
        ->assertOk()
        ->assertSee(__('time.schedule.pick_filter'), false)
        ->assertSee('wp-roster-grid-shell--hidden', false)
        ->assertSee('data-wp-roster-menu', false)
        ->assertDontSee(__('time.schedule.empty_workers'), false);

    $this->actingAs($admin)
        ->get(route('time.schedule.index', ['team' => $team->id]))
        ->assertOk()
        ->assertDontSee(__('time.schedule.pick_filter'), false)
        ->assertSee('wp-roster-grid-shell', false)
        ->assertDontSee('wp-roster-grid-shell--hidden', false);

    $unscoped = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        null,
        null,
        true,
        true,
    );
    $scoped = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        $team->id,
        $admin,
        'week',
        true,
        null,
        null,
        true,
        true,
    );

    expect($unscoped->workers)->toBe([])
        ->and($scoped->workers)->toHaveCount(1);
});

it('onthoudt de gekozen team- of locatiescope', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $other = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('teamFilter', (string) $team->id);

    expect(Cookie::queued('roster_scope'))->not->toBeNull();

    $this->actingAs($admin)
        ->withCookie('roster_scope', json_encode(['team' => $team->id, 'location' => null]))
        ->get(route('time.schedule.index'))
        ->assertOk()
        ->assertSee(e($worker->last_name), false)
        ->assertDontSee(__('time.schedule.pick_filter'), false)
        ->assertDontSee('wp-roster-grid-shell--hidden', false);

    // URL wint van de cookie: team B (leeg) toont geen worker van team A.
    $this->actingAs($admin)
        ->withCookie('roster_scope', json_encode(['team' => $team->id, 'location' => null]))
        ->get(route('time.schedule.index', ['team' => $other->id]))
        ->assertOk()
        ->assertDontSee(e($worker->last_name), false);
});

it('slaat een week op via de API', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
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
        ->assertSee('id="schedule-weekends"', false);

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-15',
        null,
        $admin,
        'month',
        true,
    );

    expect($snapshot->dates)->toHaveCount(30)
        ->and($snapshot->period)->toBe('month')
        ->and($snapshot->dayNumbers[0])->toBe(1)
        ->and($snapshot->dayNumbers[29])->toBe(30)
        ->and($snapshot->monthLabel)->toContain('2026');
});

it('onthoudt de laatst gekozen weergave via een cookie', function () {
    [$tenant, $admin] = scheduleTenant();

    $this->actingAs($admin)
        ->withCookie('roster_view', 'month')
        ->get(route('time.schedule.index'))
        ->assertOk()
        ->assertSee('wp-roster-page--month', false);

    $this->actingAs($admin)
        ->withCookie('roster_view', 'month')
        ->get(route('time.schedule.index', ['view' => 'week']))
        ->assertOk()
        ->assertDontSee('wp-roster-page--month', false);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('setView', 'month');

    $queued = Cookie::queued('roster_view');
    expect($queued)->not->toBeNull()
        ->and($queued->getValue())->toBe('month');
});

it('onthoudt de statistieken-toggle via een cookie', function () {
    [$tenant, $admin] = scheduleTenant();

    $this->actingAs($admin)
        ->withCookie('roster_stats', '0')
        ->get(route('time.schedule.index'))
        ->assertOk()
        ->assertSee('id="schedule-stats"', false)
        ->assertDontSee('id="schedule-stats" type="checkbox" checked', false);

    $this->actingAs($admin)
        ->withCookie('roster_stats', '0')
        ->get(route('time.schedule.index', ['stats' => 1]))
        ->assertOk();

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('showStats', false);

    $queued = Cookie::queued('roster_stats');
    expect($queued)->not->toBeNull()
        ->and($queued->getValue())->toBe('0');
});

it('springt met de weergaveknoppen naar de huidige week of maand', function () {
    [$tenant, $admin] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-16'));

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('weekStart', '2026-11-02')
        ->call('setView', 'week')
        ->assertSet('weekStart', '2026-09-14')
        ->set('weekStart', '2026-09-14')
        ->call('setView', 'month')
        ->assertSet('weekStart', '2026-09-01')
        ->call('setView', 'week')
        ->assertSet('weekStart', '2026-09-14');
});

it('markeert expliciete 0-minutendagen als vaste vrije dag in de worker-payload', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    // 4/5-patroon: vrijdag + weekend expliciet op 0 minuten.
    foreach ([1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 0, 6 => 0, 7 => 0] as $day => $minutes) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
            'weekday' => $day,
            'minutes' => $minutes,
        ]);
    }

    // Collega zonder patroon → geen vaste vrije dagen.
    $noPattern = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    // Collega met onvolledig patroon (alleen ma–wo, geen 0-rijen) → niets
    // wordt als vrije dag geïnterpreteerd.
    $partial = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    foreach ([1 => 456, 2 => 456, 3 => 456] as $day => $minutes) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $partial->id,
            'weekday' => $day,
            'minutes' => $minutes,
        ]);
    }

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        $team->id,
        $admin,
        'week',
        true,
        null,
        null,
        true,
        true,
    );
    $byId = collect($snapshot->workers)->keyBy('id');

    expect($byId[$worker->id]['off_weekdays'])->toBe([5, 6, 7])
        ->and($byId[$noPattern->id]['off_weekdays'])->toBe([])
        ->and($byId[$partial->id]['off_weekdays'])->toBe([]);
});

it('toont geen weekendkolommen in maandweergave als weekends uit staan', function () {
    [$tenant, $admin] = scheduleTenant();

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-15',
        null,
        $admin,
        'month',
        false,
    );

    expect($snapshot->dates)->not->toBeEmpty()
        ->and(collect($snapshot->dates)->every(
            fn (string $date) => ! Carbon::parse($date)->isWeekend(),
        ))->toBeTrue()
        ->and($snapshot->dates)->toHaveCount(22);
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
    $this->travelTo(Carbon::parse('2026-09-13'));
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
    $this->travelTo(Carbon::parse('2026-08-30'));
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
    $saved = $saved['shifts'];

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->work_date->toDateString())->toBe('2026-09-14');
});

function scheduleMonthCells(Worker $worker, string $month, bool $includeWeekends, array $values = []): array
{
    $cells = [];
    $day = Carbon::parse($month)->startOfMonth();
    $end = $day->copy()->endOfMonth();
    for (; $day->lte($end); $day->addDay()) {
        if (! $includeWeekends && $day->isWeekend()) {
            continue;
        }
        $date = $day->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $values[$date] ?? '',
        ];
    }

    return $cells;
}

it('laat weekenddiensten staan bij opslaan van een maand met verborgen weekends', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-08-30'));

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-09-01', [$worker->id], scheduleMonthCells($worker, '2026-09-01', true, [
            '2026-09-12' => '07:00-12:00',
        ]), 'month'),
        $admin->id,
    );

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-09-01', [$worker->id], scheduleMonthCells($worker, '2026-09-01', false, [
            '2026-09-14' => '08:00-16:00',
        ]), 'month', false),
        $admin->id,
    );

    Tenancy::actAs($tenant->id);
    $weekend = PlannedShift::query()->whereDate('work_date', '2026-09-12')->first();
    $monday = PlannedShift::query()->whereDate('work_date', '2026-09-14')->first();

    expect($weekend)->not->toBeNull()
        ->and($weekend->start_time)->toBe('07:00')
        ->and($monday)->not->toBeNull()
        ->and($monday->start_time)->toBe('08:00');
});

it('weigert opslaan niet door een verborgen historische weekenddienst in de lopende maand', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-16'));

    $weekend = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-05',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-09-01', [$worker->id], scheduleMonthCells($worker, '2026-09-01', false, [
            '2026-09-21' => '08:00-16:00',
        ]), 'month', false),
        $admin->id,
    );

    Tenancy::actAs($tenant->id);
    $kept = PlannedShift::query()->find($weekend->id);
    $monday = PlannedShift::query()->whereDate('work_date', '2026-09-21')->first();

    expect($kept)->not->toBeNull()
        ->and($kept->start_time)->toBe('07:00')
        ->and($kept->status)->toBe(PlannedShiftStatus::Published)
        ->and($monday)->not->toBeNull()
        ->and($monday->start_time)->toBe('08:00');
});

it('bevat schedule-webhook-events', function () {
    expect(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.saved')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.copied')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.schedule.published')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.shift_type.saved');
});

it('slaat verlof op zonder uren en weigert overlap met werk', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
    $leave = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave),
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
    $saved = $saved['shifts'];

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->kind)->toBe(ShiftTypeKind::Leave)
        ->and($saved[0]->start_time)->toBeNull()
        ->and($saved[0]->break_minutes)->toBe(0);

    $assert = app(AssertPlannedShiftNoOverlapAction::class);
    // Afwezigheidsblokken (ook omgezette deel-dag-afwezigheid) conflicteren
    // nooit met werkblokken op dezelfde dag.
    $assert->handle([
        ['worker_id' => 1, 'date' => $week, 'start' => '07:00', 'end' => '15:00', 'kind' => 'work'],
        ['worker_id' => 1, 'date' => $week, 'start' => null, 'end' => null, 'kind' => 'leave'],
    ]);

    expect(true)->toBeTrue();
});

it('maakt bij publiceren één notificatie per uitvoerder en reset read_at bij republish', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
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
    $this->travelTo(Carbon::parse('2026-09-13'));
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

    $this->travelBack();

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        $week,
        null,
        $admin,
        'week',
    );

    expect($snapshot->attendance[$worker->id.':'.$week]['status'] ?? null)->toBe('missing');
});

it('kopieert afwezigheid niet mee naar de volgende week', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    $week = scheduleWeekStart();
    $next = Carbon::parse($week)->addWeek()->toDateString();
    $nextTuesday = Carbon::parse($next)->addDay()->toDateString();
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], scheduleCells([$worker], $week, [
            $worker->id.':'.$week => 'VL',
            $worker->id.':'.Carbon::parse($week)->addDay()->toDateString() => '07:00-15:00',
        ])),
        $admin->id,
    );

    $copied = app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData($week, $next, [$worker->id]),
        $admin->id,
    );

    expect($copied)->toHaveCount(1)
        ->and($copied[0]->kind)->toBe(ShiftTypeKind::Work)
        ->and($copied[0]->work_date->toDateString())->toBe($nextTuesday)
        ->and($copied[0]->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::query()
            ->where('worker_id', $worker->id)
            ->where('kind', ShiftTypeKind::Leave->value)
            ->whereBetween('work_date', [$next, Carbon::parse($next)->addDays(6)->toDateString()])
            ->count())->toBe(0);
});

it('parses a unit code in the same cell and snapshots place on save', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-09-13'));
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
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Green),
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
    $saved = $saved['shifts'];

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
    $this->travelTo(Carbon::parse('2026-09-13'));
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
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 0, ShiftTypeColor::Green),
        $admin->id,
    );
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave),
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
    expect($saved['shifts'][0]->location_id)->toBe($siteA->id);
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
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 0, ShiftTypeColor::Green),
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
        ->and($ids)->not->toContain((int) $worker->id)
        ->and($ids)->not->toContain((int) $away->id)
        ->and($snapshot->locations)->not->toBeEmpty()
        ->and($snapshot->units)->toBeArray();

    $floaterTeam = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'clocks_all_locations' => true,
    ]);
    $floater = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $floaterTeam->id,
        'is_active' => true,
    ]);

    $withFloater = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        (int) $siteA->id,
    );
    $floaterIds = array_map(fn ($row) => (int) $row['id'], $withFloater->workers);
    expect($floaterIds)->not->toContain((int) $floater->id)
        ->and($floaterIds)->toContain((int) $home->id)
        ->and($floaterIds)->not->toContain((int) $worker->id);
});

it('groepeert workers in sectierijen bij locatiefilter en negeert uitgevinkte groepen', function () {
    [$tenant, $admin, $team] = scheduleTenant();
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Site Groep']);
    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lokaal A',
        'roster_code' => 'GA',
        'is_active' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lokaal B',
        'roster_code' => 'GB',
        'is_active' => true,
    ]);

    $inALate = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Zara',
        'last_name' => 'Alpha',
        'default_unit_id' => $unitA->id,
        'is_active' => true,
    ]);
    $inAEarly = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Ada',
        'last_name' => 'Zebra',
        'default_unit_id' => $unitA->id,
        'is_active' => true,
    ]);
    $inA = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Mia',
        'last_name' => 'Middle',
        'default_unit_id' => $unitA->id,
        'is_active' => true,
    ]);
    $inB = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Bea',
        'last_name' => 'Beta',
        'default_unit_id' => $unitB->id,
        'is_active' => true,
    ]);
    $ungrouped = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Una',
        'last_name' => 'None',
        'default_unit_id' => null,
        'is_active' => true,
    ]);
    foreach ([$inA, $inAEarly, $inALate, $inB, $ungrouped] as $person) {
        $person->locations()->sync([$location->id]);
    }

    $all = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        (int) $location->id,
        null,
        true,
    );

    expect($all->groupMode)->toBeTrue()
        ->and($all->workers[0]['group_code'])->toBe('GA')
        ->and($all->rows[0])->toMatchArray([
            'type' => 'section',
            'unit_id' => (int) $unitA->id,
        ])
        ->and($all->rows[1])->toMatchArray([
            'type' => 'worker',
            'worker_id' => (int) $inAEarly->id,
        ])
        ->and($all->rows[2])->toMatchArray([
            'type' => 'worker',
            'worker_id' => (int) $inA->id,
        ])
        ->and($all->rows[3])->toMatchArray([
            'type' => 'worker',
            'worker_id' => (int) $inALate->id,
        ]);

    $groupANames = collect($all->workers)
        ->where('default_unit_id', (int) $unitA->id)
        ->pluck('name')
        ->values()
        ->all();
    expect($groupANames)->toBe(['Ada Zebra', 'Mia Middle', 'Zara Alpha']);

    $sectionLabels = array_values(array_map(
        fn (array $row) => $row['label'] ?? null,
        array_filter($all->rows, fn (array $row) => $row['type'] === 'section'),
    ));
    expect($sectionLabels[0])->toContain('GA')
        ->and($sectionLabels)->toContain(__('time.schedule.group_ungrouped'));

    $filtered = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        (int) $location->id,
        [(int) $unitA->id],
        false,
    );

    $workerIds = array_map(fn (array $row) => (int) $row['id'], $filtered->workers);
    $sectionUnitIds = collect($filtered->rows)
        ->where('type', 'section')
        ->pluck('unit_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($workerIds)->toBe([(int) $inAEarly->id, (int) $inA->id, (int) $inALate->id])
        ->and($workerIds)->not->toContain((int) $inB->id)
        ->and($workerIds)->not->toContain((int) $ungrouped->id)
        ->and($sectionUnitIds)->toBe([(int) $unitA->id]);

    $flat = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        scheduleWeekStart(),
        null,
        $admin,
        'week',
        true,
        null,
    );

    expect($flat->groupMode)->toBeFalse()
        ->and(collect($flat->rows)->every(fn (array $row) => $row['type'] === 'worker'))->toBeTrue();
});

it('houdt canClockAt voor cel-plek onafhankelijk van default_unit_id', function () {
    [$tenant, , $team, $worker] = scheduleTenant();
    $home = Location::factory()->create(['tenant_id' => $tenant->id]);
    $away = Location::factory()->create(['tenant_id' => $tenant->id]);
    $homeUnit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $home->id,
        'roster_code' => 'HM',
        'is_active' => true,
    ]);
    $awayUnit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $away->id,
        'roster_code' => 'AW',
        'is_active' => true,
    ]);
    $worker->locations()->sync([$home->id]);
    $worker->update(['default_unit_id' => $homeUnit->id]);
    $worker->refresh()->load('locations');

    expect($worker->canClockAt((int) $home->id))->toBeTrue()
        ->and($worker->canClockAt((int) $away->id))->toBeFalse()
        ->and($worker->default_unit_id)->toBe((int) $homeUnit->id)
        ->and($awayUnit->roster_code)->toBe('AW');
});

it('opent de uurrooster-printpagina in een apart venster-patroon', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $week = scheduleWeekStart();
    $type = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'D1',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('time.schedule.print', ['week' => $week, 'view' => 'week']))
        ->assertOk()
        ->assertSee(__('time.schedule.title'), false)
        ->assertSee($worker->displayName(), false)
        ->assertSee(__('common.button.print'), false)
        ->assertSee($type->code, false)
        ->assertSee('07:00–15:00', false);
});

it('kort achternamen op de uurrooster-afdruk af tot 15 tekens', function () {
    [$tenant, $admin, $team] = scheduleTenant();
    $week = scheduleWeekStart();
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'first_name' => 'Tara',
        'last_name' => 'Vandenbroeckestraat',
    ]);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'first_name' => 'Christy',
        'last_name' => 'De Graeve',
    ]);

    expect(Worker::printDisplayName('Tara', 'Vandenbroeckestraat'))->toBe('Tara Vandenbroeckest.')
        ->and(Worker::printDisplayName('Christy', 'De Graeve'))->toBe('Christy De Graeve');

    $this->actingAs($admin)
        ->get(route('time.schedule.print', ['week' => $week, 'view' => 'week']))
        ->assertOk()
        ->assertSee('Tara Vandenbroeckest.', false)
        ->assertDontSee('Vandenbroeckestraat', false)
        ->assertSee('Christy De Graeve', false);
});

// ---- Historische roostervergrendeling (RosterHistoryLock) ----

it('beoordeelt vergrendeling op de app-tijdzone, niet op UTC', function () {
    // UTC 23:30 op 14 okt = Brussel 15 okt 01:30 (CEST): "vandaag" = de 15e.
    Carbon::setTestNow(Carbon::parse('2026-10-14 23:30', 'UTC'));

    expect(RosterHistoryLock::today())->toBe('2026-10-15')
        ->and(RosterHistoryLock::isLockedDate('2026-10-14'))->toBeTrue()
        ->and(RosterHistoryLock::isLockedDate('2026-10-15'))->toBeFalse()
        ->and(RosterHistoryLock::isLockedDate('2026-10-16'))->toBeFalse();

    // Een blok tot 23:59 op dag D is op D+1 vergrendeld.
    $shift = new PlannedShift(['work_date' => '2026-10-14', 'start_time' => '16:00', 'end_time' => '23:59']);
    expect(RosterHistoryLock::isLocked($shift))->toBeTrue();
});

it('houdt een gemengde week bewerkbaar: identiek verleden blijft en toekomst schrijft', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15')); // donderdag

    $locked = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'description' => 'Vaste notitie',
        'status' => PlannedShiftStatus::Published,
    ]);

    $result = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-10-12', [$worker->id], scheduleCells([$worker], '2026-10-12', [
            $worker->id.':2026-10-14' => '07:00-15:00',
            $worker->id.':2026-10-16' => '09:00-17:00',
        ])),
        $admin->id,
    );

    $fresh = $locked->fresh();
    expect($result['shifts'])->toHaveCount(1)
        ->and($result['shifts'][0]->work_date->toDateString())->toBe('2026-10-16')
        ->and($fresh->id)->toBe($locked->id)
        ->and($fresh->description)->toBe('Vaste notitie')
        ->and($fresh->start_time)->toBe('07:00');
});

it('behoudt vergrendelde dagen via keep-cellen zelfs zonder exacte round-trip', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15')); // donderdag

    // Vrije dienst met pauze: de gridcel "07:00-15:00" kan break_minutes niet
    // dragen — zonder keep zou dit als wijziging gelockt falen.
    $locked = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 30,
        'status' => PlannedShiftStatus::Published,
    ]);

    $cells = array_map(
        fn (array $cell) => $cell['date'] === '2026-10-14'
            ? ['worker_id' => $cell['worker_id'], 'date' => $cell['date'], 'raw' => '', 'keep' => true]
            : $cell,
        scheduleCells([$worker], '2026-10-12', [
            $worker->id.':2026-10-16' => '09:00-17:00',
        ]),
    );

    $result = app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-10-12', [$worker->id], $cells),
        $admin->id,
    );

    $fresh = $locked->fresh();
    expect($result['shifts'])->toHaveCount(1)
        ->and($result['shifts'][0]->work_date->toDateString())->toBe('2026-10-16')
        ->and($fresh->id)->toBe($locked->id)
        ->and($fresh->break_minutes)->toBe(30);
});

it('vlagt wijzigen, legen en aanmaken op vergrendelde dagen in de grid', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15'));

    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'status' => PlannedShiftStatus::Published,
    ]);

    // Wijzigen van een bestaand blok.
    expect(fn () => app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-10-12', [$worker->id], scheduleCells([$worker], '2026-10-12', [
            $worker->id.':2026-10-14' => '08:00-16:00',
        ])),
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_days');

    // Legen van de cel = verwijderpoging.
    expect(fn () => app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-10-12', [$worker->id], scheduleCells([$worker], '2026-10-12')),
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_days');

    // Nieuw blok op een lege vergrendelde dag van een andere worker.
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    expect(fn () => app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData('2026-10-12', [$colleague->id], scheduleCells([$colleague], '2026-10-12', [
            $colleague->id.':2026-10-14' => '07:00-15:00',
        ])),
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_days');

    // Niets is aangeraakt; de rij van de eerste worker overleeft saves voor
    // andere workers die hem niet in de payload hebben.
    $block = PlannedShift::query()->first();
    expect(PlannedShift::query()->count())->toBe(1)
        ->and($block->start_time)->toBe('07:00')
        ->and($block->worker_id)->toBe($worker->id);
});

it('weigert de dag-editor op een vergrendelde dag, vandaag blijft bewerkbaar', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15'));

    $today = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-15',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'status' => PlannedShiftStatus::Published,
    ]);

    expect(fn () => app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData($worker->id, '2026-10-14', [[
            'id' => null,
            'shift_type_id' => null,
            'start_time' => '07:00',
            'end_time' => '15:00',
            'break_minutes' => 0,
            'unit_id' => null,
            'description' => null,
        ]]),
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_day');

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData($worker->id, '2026-10-15', [[
            'id' => $today->id,
            'shift_type_id' => null,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 0,
            'unit_id' => null,
            'description' => 'Aangepast',
        ]]),
        $admin->id,
    );

    expect($today->fresh()->start_time)->toBe('08:00');
});

it('weigert een vervanging op een vergrendelde dag', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15'));

    $leave = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave),
        $admin->id,
    );
    $shift = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'status' => PlannedShiftStatus::Published,
    ]);
    $replacement = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    expect(fn () => app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [$shift->id],
        $replacement->id,
        $leave->id,
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_day');
});

it('weigert kopiëren naar een verleden week en staat verleden-bron naar toekomst toe', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15'));

    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-05',
        'start_time' => '07:00',
        'end_time' => '15:00',
    ]);

    expect(fn () => app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData('2026-10-05', '2026-10-12', [$worker->id]),
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'locked_day');

    $copied = app(CopyWeekAction::class)->handle(
        $tenant,
        new CopyWeekData('2026-10-05', '2026-10-19', [$worker->id]),
        $admin->id,
    );

    expect($copied)->toHaveCount(1)
        ->and($copied[0]->work_date->toDateString())->toBe('2026-10-19')
        ->and($copied[0]->status)->toBe(PlannedShiftStatus::Draft);
});

it('publiceert enkel niet-vergrendelde dagen en notificeert enkel geflipte workers', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $this->travelTo(Carbon::parse('2026-10-15'));

    $past = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'status' => PlannedShiftStatus::Draft,
    ]);
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $future = PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $colleague->id,
        'work_date' => '2026-10-16',
        'status' => PlannedShiftStatus::Draft,
    ]);

    $count = app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData('2026-10-12', [$worker->id, $colleague->id]),
        $admin->id,
    );

    expect($count)->toBe(1)
        ->and($past->fresh()->status)->toBe(PlannedShiftStatus::Draft)
        ->and($future->fresh()->status)->toBe(PlannedShiftStatus::Published)
        ->and(WorkerNotification::query()
            ->where('worker_id', $colleague->id)
            ->where('type', WorkerNotificationType::RosterPublished->value)
            ->count())->toBe(1)
        ->and(WorkerNotification::query()
            ->where('worker_id', $worker->id)
            ->where('type', WorkerNotificationType::RosterPublished->value)
            ->count())->toBe(0);
});

// ---- Geauditeerde historische correctie (CorrectPlannedShiftAction) ----

it('corrigeert een vergrendeld blok met reden, audit en notificatie', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    $results = app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [[
            'type' => 'update',
            'planned_shift_id' => $shift->id,
            'block' => ['start_time' => '10:00', 'end_time' => '18:00', 'break_minutes' => 30, 'description' => 'Typefout hersteld'],
        ]],
        'Verkeerde tijden ingepland',
        $admin->id,
    );

    $fresh = $shift->fresh();
    expect($fresh->start_time)->toBe('10:00')
        ->and($fresh->end_time)->toBe('18:00')
        ->and((int) $fresh->break_minutes)->toBe(30)
        ->and($fresh->description)->toBe('Typefout hersteld')
        ->and($fresh->work_date->toDateString())->toBe('2026-10-13')
        ->and($fresh->status)->toBe(PlannedShiftStatus::Published)
        ->and($results)->toHaveCount(1);

    $audit = AuditLog::query()->where('action', 'planned_shift.corrected')->latest('id')->first();
    expect($audit)->not->toBeNull();
    $payload = $audit->payload;
    expect($payload['operation'])->toBe('update')
        ->and($payload['reason'])->toBe('Verkeerde tijden ingepland')
        ->and($payload['before']['start_time'])->toBe('09:00')
        ->and($payload['after']['start_time'])->toBe('10:00')
        ->and($payload['before']['work_date'])->toBe('2026-10-13')
        ->and($payload['after']['work_date'])->toBe('2026-10-13')
        ->and((int) $audit->user_id)->toBe($admin->id);

    $note = WorkerNotification::query()
        ->where('worker_id', $worker->id)
        ->where('type', WorkerNotificationType::RosterCorrected->value)
        ->first();
    expect($note)->not->toBeNull()
        ->and($note->reference_id)->toBe('2026-10-13:'.$shift->id);
});

it('weigert correctie zonder reden', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Draft,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [['type' => 'update', 'planned_shift_id' => $shift->id, 'block' => ['start_time' => '10:00', 'end_time' => '18:00']]],
        '   ',
        $admin->id,
    );
})->throws(InvalidArgumentException::class);

it('verwijdert een vergrendeld blok via de correctieroute', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [['type' => 'delete', 'planned_shift_id' => $shift->id]],
        'Ten onrechte ingepland',
        $admin->id,
    );

    expect(PlannedShift::query()->whereKey($shift->id)->exists())->toBeFalse();

    $audit = AuditLog::query()->where('action', 'planned_shift.corrected')->latest('id')->first();
    expect($audit->payload['operation'])->toBe('delete')
        ->and($audit->payload['before']['work_date'])->toBe('2026-10-13')
        ->and($audit->payload['after'])->toBeNull();

    expect(WorkerNotification::query()
        ->where('worker_id', $worker->id)
        ->where('type', WorkerNotificationType::RosterCorrected->value)
        ->where('reference_id', '2026-10-13:'.$shift->id)
        ->exists())->toBeTrue();
});

it('maakt een retro-blok aan op een verleden datum via de correctieroute', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    // Published blok in dezelfde week → nieuw blok erft published-status.
    PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-12',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    $results = app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [[
            'type' => 'create',
            'worker_id' => $worker->id,
            'block' => ['work_date' => '2026-10-13', 'start_time' => '20:00', 'end_time' => '22:00'],
        ]],
        'Avonddienst vergeten in te plannen',
        $admin->id,
    );

    $created = PlannedShift::query()
        ->where('worker_id', $worker->id)
        ->whereDate('work_date', '2026-10-13')
        ->first();
    expect($created)->not->toBeNull()
        ->and($created->start_time)->toBe('20:00')
        ->and($created->status)->toBe(PlannedShiftStatus::Published)
        ->and($results[0]['type'])->toBe('create');

    $audit = AuditLog::query()->where('action', 'planned_shift.corrected')->latest('id')->first();
    expect($audit->payload['operation'])->toBe('create')
        ->and($audit->payload['before'])->toBeNull()
        ->and($audit->payload['after']['work_date'])->toBe('2026-10-13');
});

it('verplaatst een blok van work_date via de correctieroute en logt beide datums', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [[
            'type' => 'update',
            'planned_shift_id' => $shift->id,
            'block' => ['work_date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '12:00'],
        ]],
        'Dienst stond op de verkeerde dag',
        $admin->id,
    );

    expect($shift->fresh()->work_date->toDateString())->toBe('2026-10-20');

    $audit = AuditLog::query()->where('action', 'planned_shift.corrected')->latest('id')->first();
    expect($audit->payload['before']['work_date'])->toBe('2026-10-13')
        ->and($audit->payload['after']['work_date'])->toBe('2026-10-20');
});

it('voert een retro-vervanging atomair uit en notificeert beide workers', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [
            ['type' => 'delete', 'planned_shift_id' => $shift->id],
            [
                'type' => 'create',
                'worker_id' => $colleague->id,
                'block' => ['work_date' => '2026-10-13', 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ],
        'Vervanging was nooit in het rooster gezet',
        $admin->id,
    );

    expect(PlannedShift::query()->whereKey($shift->id)->exists())->toBeFalse()
        ->and(PlannedShift::query()
            ->where('worker_id', $colleague->id)
            ->whereDate('work_date', '2026-10-13')
            ->exists())->toBeTrue()
        ->and(WorkerNotification::query()
            ->where('worker_id', $worker->id)
            ->where('type', WorkerNotificationType::RosterCorrected->value)
            ->where('reference_id', '2026-10-13:'.$shift->id)
            ->exists())->toBeTrue();

    $replacementId = (int) PlannedShift::query()
        ->where('worker_id', $colleague->id)
        ->whereDate('work_date', '2026-10-13')
        ->value('id');
    expect(WorkerNotification::query()
        ->where('worker_id', $colleague->id)
        ->where('type', WorkerNotificationType::RosterCorrected->value)
        ->where('reference_id', '2026-10-13:'.$replacementId)
        ->exists())->toBeTrue();

    expect(AuditLog::query()->where('action', 'planned_shift.corrected')->count())->toBe(2);
});

it('notificeert niet als een latere op in dezelfde call faalt', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    try {
        app(CorrectPlannedShiftAction::class)->handle(
            $tenant,
            [
                ['type' => 'delete', 'planned_shift_id' => $shift->id],
                ['type' => 'bogus_op', 'planned_shift_id' => 999999],
            ],
            'Tweede op faalt',
            $admin->id,
        );
        $this->fail('Verwachte een exceptie');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toBe('time.schedule.errors.correction_unknown_op');
    }

    // Alles teruggedraaid: blok bestaat nog, geen audit, geen notificatie.
    expect(PlannedShift::query()->whereKey($shift->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'planned_shift.corrected')->count())->toBe(0)
        ->and(WorkerNotification::query()
            ->where('type', WorkerNotificationType::RosterCorrected->value)
            ->count())->toBe(0);
});

it('weigert overlap bij een correctie-create', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [[
            'type' => 'create',
            'worker_id' => $worker->id,
            'block' => ['work_date' => '2026-10-13', 'start_time' => '16:00', 'end_time' => '20:00'],
        ]],
        'Overlap-test',
        $admin->id,
    );
})->throws(RosterValidationException::class, 'overlap');

it('blijft binnen de eigen tenant', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $other = Tenant::factory()->create(['has_time_module' => true]);
    $otherWorker = Worker::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);
    $foreign = PlannedShift::create([
        'tenant_id' => $other->id,
        'worker_id' => $otherWorker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    app(CorrectPlannedShiftAction::class)->handle(
        $tenant,
        [['type' => 'delete', 'planned_shift_id' => $foreign->id]],
        'Tenant-isolatie test',
        $admin->id,
    );
})->throws(RosterValidationException::class, 'unknown_block');

it('laat enkel tenant-admins corrigeren via de policy', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);
    $superuser = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_ADMIN,
        'is_superuser' => true,
    ]);
    $otherAdmin = User::factory()->create([
        'tenant_id' => Tenant::factory()->create(['has_time_module' => true])->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    expect($admin->can('correct', $shift))->toBeTrue()
        ->and($employee->can('correct', $shift))->toBeFalse()
        ->and($superuser->can('correct', $shift))->toBeFalse()
        ->and($otherAdmin->can('correct', $shift))->toBeFalse();
});

it('toont de dag-editor readonly op een vergrendelde dag en blokkeert opslaan', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openDayEditor', $worker->id, '2026-10-13')
        ->assertSet('dayEditorLocked', true)
        ->assertSee(__('time.schedule.day_editor.locked_hint'), false)
        ->call('saveDayEditor')
        ->assertSet('dayEditorError', __('time.schedule.errors.locked_day'));
});

it('laat een tenant-admin een vergrendeld blok corrigeren via de modal', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openDayEditor', $worker->id, '2026-10-13')
        ->call('openCorrection', $shift->id)
        ->set('correctionBlock.start_time', '10:00')
        ->set('correctionBlock.end_time', '18:00')
        ->set('correctionReason', 'Tijden klopten niet')
        ->call('saveCorrection')
        ->assertSet('correctionError', '')
        ->assertSet('showCorrectionModal', false);

    expect($shift->fresh()->start_time)->toBe('10:00')
        ->and(AuditLog::query()->where('action', 'planned_shift.corrected')->count())->toBe(1);
});

it('weigert niet-admins de correctie-ingang in de UI', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);

    $shift = PlannedShift::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-13',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'kind' => ShiftTypeKind::Work,
        'status' => PlannedShiftStatus::Published,
    ]);

    Livewire::actingAs($employee)
        ->test(RosterIndex::class)
        ->call('openDayEditor', $worker->id, '2026-10-13')
        ->assertSet('dayEditorLocked', true)
        ->call('openCorrection', $shift->id)
        ->assertForbidden();
});

it('beoordeelt vergrendeling rond middernacht en DST-overgangen op de app-tijdzone', function () {
    // Middernacht: gisteren vergrendeld, vandaag bewerkbaar.
    $this->travelTo(Carbon::parse('2026-10-15 00:30:00', 'Europe/Brussels'));
    expect(RosterHistoryLock::today())->toBe('2026-10-15')
        ->and(RosterHistoryLock::isLockedDate('2026-10-14'))->toBeTrue()
        ->and(RosterHistoryLock::isLockedDate('2026-10-15'))->toBeFalse();

    // Zomertijd→wintertijd (25 okt 2026, klok terug): datumsgrens blijft zuiver.
    $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'Europe/Brussels'));
    expect(RosterHistoryLock::today())->toBe('2026-10-25')
        ->and(RosterHistoryLock::isLockedDate('2026-10-24'))->toBeTrue()
        ->and(RosterHistoryLock::isLockedDate('2026-10-25'))->toBeFalse();

    // Wintertijd→zomertijd (29 mrt 2026, klok vooruit): idem.
    $this->travelTo(Carbon::parse('2026-03-29 10:00:00', 'Europe/Brussels'));
    expect(RosterHistoryLock::today())->toBe('2026-03-29')
        ->and(RosterHistoryLock::isLockedDate('2026-03-28'))->toBeTrue()
        ->and(RosterHistoryLock::isLockedDate('2026-03-29'))->toBeFalse();
});

it('laat de shift van vandaag bewerkbaar, ook als er al geprikt is', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    [$tenant, $admin, $team, $worker] = scheduleTenant();
    $today = '2026-10-15';

    // Bestaande prik op vandaag vergrendelt niets (punch-lock is buiten scope).
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_at' => Carbon::parse($today.' 07:05:00'),
        'clock_out_at' => null,
    ]);

    $result = app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $today, [
            ['start_time' => '07:00', 'end_time' => '15:00'],
        ]),
        $admin->id,
    );

    expect($result['created'])->toBe(1)
        ->and(PlannedShift::query()
            ->where('worker_id', $worker->id)
            ->whereDate('work_date', $today)
            ->exists())->toBeTrue();
});

it('levert een uren-linkhint per werknemer in het rooster-payload', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    $this->actingAs($admin)
        ->get(route('time.schedule.index', ['team' => $team->id]))
        ->assertOk()
        ->assertSeeHtml('worker_hours_hint')
        ->assertSeeHtml('time/shifts');
});

it('verbergt en toont de statistiek-kolommen via de toggle', function () {
    [$tenant, $admin, $team, $worker] = scheduleTenant();

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->set('teamFilter', $team->id)
        ->assertViewHas('gridPayload', fn (array $payload) => count($payload['hours_columns'] ?? []) > 0)
        ->set('showStats', false)
        ->assertViewHas('gridPayload', fn (array $payload) => ($payload['hours_columns'] ?? []) === [])
        ->set('showStats', true)
        ->assertViewHas('gridPayload', fn (array $payload) => count($payload['hours_columns'] ?? []) > 0);
});
