<?php

declare(strict_types=1);

use App\Actions\Team\SaveWorkerHolidayAgreementAction;
use App\Actions\Team\SyncWorkerWeekdayMinutesAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ResolveWorkerMonthNormAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SummarizeWorkerMonthFactsAction;
use App\Actions\Time\SummarizeWorkerWeekHoursAction;
use App\Data\Time\SavePlannedDayData;
use App\Enums\ClockSource;
use App\Enums\HolidayAgreementEffect;
use App\Enums\MonthNormStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkShiftStatus;
use App\Models\BelgianPublicHoliday;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerHolidayAgreement;
use App\Models\WorkerUnavailability;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\BelgianPublicHolidayCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Tenancy::forget());

function monthNormTenant(int $weeklyMinutes = 2280): array
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
        'weekly_contract_minutes' => $weeklyMinutes,
    ]);

    return [$tenant, $admin, $team, $worker];
}

/** @param  array<int, int>  $pattern */
function monthNormSync(Worker $worker, array $pattern): void
{
    app(SyncWorkerWeekdayMinutesAction::class)->handle(
        (int) $worker->tenant_id,
        $worker,
        $pattern,
    );
}

/** @param  array<int, int>  $pattern */
function monthNormExpected(string $month, array $pattern): int
{
    $start = Carbon::parse($month)->startOfMonth();
    $end = $start->copy()->endOfMonth();
    $total = 0;
    for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
        $total += $pattern[(int) $day->dayOfWeekIso];
    }

    return $total;
}

/** @return array<int, int> */
function monthNormFiveDayPattern(int $dayMinutes = 456): array
{
    return [1 => $dayMinutes, 2 => $dayMinutes, 3 => $dayMinutes, 4 => $dayMinutes, 5 => $dayMinutes, 6 => 0, 7 => 0];
}

function monthNormAgree(Worker $worker, string $date, HolidayAgreementEffect $effect, ?string $replacement = null): void
{
    app(SaveWorkerHolidayAgreementAction::class)->handle(
        (int) $worker->tenant_id,
        $worker,
        $date,
        $effect,
        $replacement,
    );
}

it('berekent de tien feestdagen per jaar zonder tenant', function () {
    expect(BelgianPublicHolidayCalendar::easter(2026)->toDateString())->toBe('2026-04-05')
        ->and(BelgianPublicHolidayCalendar::datesForYear(2026)['easter_monday'])->toBe('2026-04-06')
        ->and(BelgianPublicHolidayCalendar::datesForYear(2026)['ascension'])->toBe('2026-05-14')
        ->and(BelgianPublicHolidayCalendar::datesForYear(2026)['whit_monday'])->toBe('2026-05-25')
        ->and(BelgianPublicHolidayCalendar::datesForYear(2025)['easter_monday'])->toBe('2025-04-21')
        ->and(Schema::hasColumn('belgian_public_holidays', 'tenant_id'))->toBeFalse()
        ->and(BelgianPublicHoliday::query()->where('year', 2026)->count())->toBe(10)
        ->and(BelgianPublicHoliday::query()->where('year', 2019)->count())->toBe(0)
        ->and(BelgianPublicHoliday::query()->where('year', 2026)->where('code', 'easter_monday')->first()->date->toDateString())
        ->toBe('2026-04-06');
});

it('telt een maand zonder feestdag als som van de werkregeling', function () {
    [, , , $worker] = monthNormTenant();
    $pattern = monthNormFiveDayPattern();
    monthNormSync($worker, $pattern);

    $norm = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];

    expect($norm->status)->toBe(MonthNormStatus::Available)
        ->and($norm->minutes)->toBe(monthNormExpected('2026-02-01', $pattern));
});

it('gebruikt ma–vr als norm zonder patroon en blijft incomplete na een normwijziging', function () {
    [$tenant, $admin, $team, $worker] = monthNormTenant();
    $pattern = monthNormFiveDayPattern();

    $missing = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];
    expect($missing->status)->toBe(MonthNormStatus::Available)
        ->and($missing->minutes)->toBe(monthNormExpected('2026-02-01', $pattern));

    expect(fn () => monthNormSync($worker, [1 => 456, 2 => 456, 3 => 456, 4 => 456, 5 => 456, 6 => 0]))
        ->toThrow(ValidationException::class);

    monthNormSync($worker, $pattern);
    $worker->update(['weekly_contract_minutes' => 1800]);

    $stale = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];
    expect($stale->status)->toBe(MonthNormStatus::Incomplete)
        ->and($stale->minutes)->toBeNull();

    $week = app(SummarizeWorkerWeekHoursAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-02')[(int) $worker->id];
    expect($week->contractMinutes)->toBe(1800)
        ->and($week->differenceMinutes())->toBe(-1800);

    $snapshot = app(ListRosterWeekAction::class)->handle((int) $tenant->id, '2026-02-02', (int) $team->id, $admin);
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);
    expect($row)->not->toHaveKey('month_hours')
        ->and($row['week_hours']['contract_minutes'])->toBe(1800)
        ->and($row['week_hours']['difference_minutes'])->toBe(-1800);
});

it('leidt de maandnorm niet af uit onbeschikbaarheid of ingeplande diensten', function () {
    [$tenant, , , $worker] = monthNormTenant();
    $pattern = monthNormFiveDayPattern();
    monthNormSync($worker, $pattern);
    WorkerUnavailability::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'weekday' => 1,
    ]);
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-02-07',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Draft,
    ]);

    $norm = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];
    $facts = app(SummarizeWorkerMonthFactsAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];

    expect($norm->minutes)->toBe(monthNormExpected('2026-02-01', $pattern))
        ->and($facts->plannedMinutes)->toBe(4 * 60);
});

it('telt een feestdag zonder afspraak als niet gewerkt in de norm', function () {
    [$tenant, , , $worker] = monthNormTenant();
    monthNormSync($worker, monthNormFiveDayPattern());
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-05-04',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Published,
    ]);

    $norm = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-05-01')[(int) $worker->id];
    $facts = app(SummarizeWorkerMonthFactsAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-05-01')[(int) $worker->id];

    expect($norm->status)->toBe(MonthNormStatus::Available)
        ->and($norm->minutes)->toBe(monthNormExpected('2026-05-01', monthNormFiveDayPattern()) - 3 * 456)
        ->and($facts->plannedMinutes)->toBe(4 * 60);
});

it('past none en dropped toe zonder de andere dagen te wijzigen', function () {
    [, , , $worker] = monthNormTenant();
    $pattern = monthNormFiveDayPattern();
    monthNormSync($worker, $pattern);
    foreach (['2026-05-01', '2026-05-14', '2026-05-25'] as $date) {
        monthNormAgree($worker, $date, HolidayAgreementEffect::None);
    }

    $kept = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-05-01')[(int) $worker->id];
    expect($kept->status)->toBe(MonthNormStatus::Available)
        ->and($kept->minutes)->toBe(monthNormExpected('2026-05-01', $pattern));

    monthNormAgree($worker, '2026-05-01', HolidayAgreementEffect::Dropped);
    $dropped = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-05-01')[(int) $worker->id];
    expect($dropped->minutes)->toBe(monthNormExpected('2026-05-01', $pattern) - $pattern[5]);
});

it('overschrijft een vervangingsdag binnen de maand, ongeacht de invoegvolgorde', function () {
    [$tenant, , $team, $first] = monthNormTenant();
    $second = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'weekly_contract_minutes' => 2280,
    ]);
    $pattern = [1 => 600, 2 => 480, 3 => 400, 4 => 400, 5 => 400, 6 => 0, 7 => 0];
    monthNormSync($first, $pattern);
    monthNormSync($second, $pattern);

    $pairs = [
        $first->id => [
            ['2026-11-01', '2026-11-02'],
            ['2026-11-11', '2026-11-14'],
        ],
        $second->id => [
            ['2026-11-11', '2026-11-14'],
            ['2026-11-01', '2026-11-02'],
        ],
    ];
    foreach ($pairs as $workerId => $agreements) {
        $worker = Worker::query()->findOrFail($workerId);
        foreach ($agreements as [$holiday, $replacement]) {
            monthNormAgree($worker, $holiday, HolidayAgreementEffect::Replaced, $replacement);
        }
    }

    $rows = app(ResolveWorkerMonthNormAction::class)->handle(
        (int) $tenant->id,
        [(int) $first->id, (int) $second->id],
        '2026-11-01',
    );
    $expected = monthNormExpected('2026-11-01', $pattern)
        - $pattern[7]
        - $pattern[1] + $pattern[7]
        - $pattern[3]
        - $pattern[6] + $pattern[3];

    expect($rows[(int) $first->id]->status)->toBe(MonthNormStatus::Available)
        ->and($rows[(int) $first->id]->minutes)->toBe($expected)
        ->and($rows[(int) $second->id]->minutes)->toBe($expected);
});

it('zet vervangingsuren alleen in de maand van de vervangingsdatum', function () {
    [, , , $worker] = monthNormTenant();
    $pattern = [1 => 600, 2 => 480, 3 => 400, 4 => 400, 5 => 400, 6 => 0, 7 => 0];
    monthNormSync($worker, $pattern);
    monthNormAgree($worker, '2026-11-01', HolidayAgreementEffect::None);
    monthNormAgree($worker, '2026-11-11', HolidayAgreementEffect::Replaced, '2026-12-05');

    $november = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-11-01')[(int) $worker->id];
    expect($november->status)->toBe(MonthNormStatus::Available)
        ->and($november->minutes)->toBe(monthNormExpected('2026-11-01', $pattern) - $pattern[3]);

    $decemberWithoutChristmas = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-12-01')[(int) $worker->id];
    expect($decemberWithoutChristmas->status)->toBe(MonthNormStatus::Available)
        ->and($decemberWithoutChristmas->minutes)->toBe(
            monthNormExpected('2026-12-01', $pattern) - $pattern[5] - $pattern[6] + $pattern[3]
        );

    monthNormAgree($worker, '2026-12-25', HolidayAgreementEffect::None);
    $december = app(ResolveWorkerMonthNormAction::class)->handle((int) $worker->tenant_id, [(int) $worker->id], '2026-12-01')[(int) $worker->id];
    expect($december->status)->toBe(MonthNormStatus::Available)
        ->and($december->minutes)->toBe(monthNormExpected('2026-12-01', $pattern) - $pattern[6] + $pattern[3]);
});

it('weigert een ongedekte of botsende vervangingsdag en past geen deelcorrectie toe', function () {
    [$tenant, , , $worker] = monthNormTenant();
    monthNormSync($worker, monthNormFiveDayPattern());
    monthNormAgree($worker, '2026-11-01', HolidayAgreementEffect::None);

    expect(fn () => monthNormAgree($worker, '2026-11-11', HolidayAgreementEffect::Replaced, '2041-03-03'))
        ->toThrow(ValidationException::class);

    monthNormAgree($worker, '2026-11-11', HolidayAgreementEffect::Replaced, '2026-11-14');
    $agreement = WorkerHolidayAgreement::query()->where('worker_id', $worker->id)->whereDate('holiday_date', '2026-11-11')->first();
    $agreement->update(['replacement_date' => '2041-03-03']);

    $uncovered = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-11-01')[(int) $worker->id];
    expect($uncovered->status)->toBe(MonthNormStatus::Incomplete)
        ->and($uncovered->minutes)->toBeNull();

    $agreement->update(['replacement_date' => '2026-11-14']);
    WorkerHolidayAgreement::query()->where('worker_id', $worker->id)->whereDate('holiday_date', '2026-11-01')->update([
        'effect' => HolidayAgreementEffect::Replaced,
        'replacement_date' => '2026-11-14',
    ]);

    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-03',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Draft,
    ]);

    $conflict = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-11-01')[(int) $worker->id];
    $facts = app(SummarizeWorkerMonthFactsAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-11-01')[(int) $worker->id];

    expect($conflict->status)->toBe(MonthNormStatus::Conflict)
        ->and($conflict->minutes)->toBeNull()
        ->and($facts->plannedMinutes)->toBe(4 * 60);
});

it('toont norm en status ook aan superusers en laat de weekpayload met rust', function () {
    [$tenant, $admin, $team, $worker] = monthNormTenant(2220);
    $pattern = monthNormFiveDayPattern(444);
    monthNormSync($worker, $pattern);
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);
    $super = User::factory()->superuser()->create();
    $outsider = User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'role' => User::ROLE_ADMIN,
    ]);

    expect(Gate::forUser($admin)->allows('update', $worker))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('update', $worker))->toBeTrue()
        ->and(Gate::forUser($super)->allows('update', $worker))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('update', $worker))->toBeFalse();

    $list = app(ListRosterWeekAction::class);
    $forAdminSnapshot = $list->handle((int) $tenant->id, '2026-02-01', (int) $team->id, $admin, 'month');
    $forSuperSnapshot = $list->handle((int) $tenant->id, '2026-02-01', (int) $team->id, $super, 'month');
    $forAdmin = collect($forAdminSnapshot->workers)->firstWhere('id', $worker->id);
    $forSuper = collect($forSuperSnapshot->workers)->firstWhere('id', $worker->id);
    $minutes = monthNormExpected('2026-02-01', $pattern);
    $superKeys = array_column($forSuperSnapshot->hoursColumns, 'key');

    expect($forAdmin['month_hours']['norm_status'])->toBe(MonthNormStatus::Available->value)
        ->and($forAdmin['month_hours']['contract_minutes'])->toBe($minutes)
        ->and($forAdmin['month_hours']['difference_minutes'])->toBe(-$minutes)
        ->and($forSuper['month_hours']['planned_minutes'])->toBe(0)
        ->and($forSuper['month_hours'])->toHaveKey('worked_minutes')
        ->and($forSuper['month_hours'])->toHaveKey('absences')
        ->and($forSuper['month_hours']['norm_status'])->toBe(MonthNormStatus::Available->value)
        ->and($forSuper['month_hours']['contract_minutes'])->toBe($minutes)
        ->and($forSuper['month_hours']['difference_minutes'])->toBe(-$minutes)
        ->and($superKeys)->toContain('norm')
        ->and($superKeys)->toContain('difference')
        ->and($forSuper['month_hours']['figures'])->toHaveCount(count($superKeys));

    $week = collect($list->handle((int) $tenant->id, '2026-02-02', (int) $team->id, $admin)->workers)
        ->firstWhere('id', $worker->id);
    expect($week)->not->toHaveKey('month_hours')
        ->and($week['week_hours'])->toHaveKey('contract_minutes')
        ->and($week['week_hours']['contract_minutes'])->toBe(2220);
});

it('bewaart een feestdag als kalenderdatum zonder tijdstip', function () {
    [, , , $worker] = monthNormTenant();
    monthNormAgree($worker, '2026-05-01', HolidayAgreementEffect::Replaced, '2026-05-04');

    $raw = DB::table('worker_holiday_agreements')->where('worker_id', $worker->id)->first();

    expect($raw->holiday_date)->toBe('2026-05-01')
        ->and($raw->replacement_date)->toBe('2026-05-04')
        ->and(WorkerHolidayAgreement::query()->where('worker_id', $worker->id)->whereDate('holiday_date', '2026-05-01')->exists())
        ->toBeTrue();
});

it('toont de maandnorm met feestdagen als niet gewerkt zonder afspraak', function () {
    [$tenant, $admin, $team, $worker] = monthNormTenant();
    monthNormSync($worker, monthNormFiveDayPattern());
    $expected = monthNormExpected('2026-05-01', monthNormFiveDayPattern()) - 3 * 456;

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-05-01',
        (int) $team->id,
        $admin,
        'month',
    );
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);
    $keys = array_column($snapshot->hoursColumns, 'key');
    $figures = $row['month_hours']['figures'];

    expect($row['month_hours']['norm_status'])->toBe(MonthNormStatus::Available->value)
        ->and($row['month_hours']['contract_minutes'])->toBe($expected)
        ->and($row['month_hours']['difference_minutes'])->toBe(-$expected)
        ->and($figures[array_search('norm', $keys, true)]['value'])->not->toBeNull()
        ->and($figures[array_search('planned', $keys, true)]['value'])->toBe('0');
});

it('telt maandfeiten over de hele kalendermaand, ook als de norm ontbreekt', function () {
    [$tenant, $admin, , $worker] = monthNormTenant();
    $worker->update(['weekly_contract_minutes' => null]);

    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-02-07',
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'status' => PlannedShiftStatus::Draft,
    ]);
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => WorkShiftStatus::Closed,
        'clock_in_at' => Carbon::parse('2026-03-01 00:00:00', 'Europe/Brussels'),
        'clock_out_at' => Carbon::parse('2026-03-01 04:00:00', 'Europe/Brussels'),
        'clock_out_source' => ClockSource::ClockPointQr,
        'total_break_minutes' => 0,
    ]);
    WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => WorkShiftStatus::Open,
        'clock_in_at' => Carbon::parse('2026-01-20 08:00:00', 'Europe/Brussels'),
        'clock_out_at' => null,
        'total_break_minutes' => 0,
    ]);

    $facts = app(SummarizeWorkerMonthFactsAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];
    $norm = app(ResolveWorkerMonthNormAction::class)->handle((int) $tenant->id, [(int) $worker->id], '2026-02-01')[(int) $worker->id];

    expect($facts->absences['leave']['days'])->toBe(1)
        ->and($facts->workedMinutes)->toBe(0)
        ->and($facts->registrationIncomplete)->toBeTrue()
        ->and($norm->status)->toBe(MonthNormStatus::Available)
        ->and($norm->minutes)->toBe(monthNormExpected('2026-02-01', monthNormFiveDayPattern()));

    $day = Carbon::parse('2026-12-10')->toDateString();
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $day, [[
            'start_time' => '08:00',
            'end_time' => '12:00',
            'break_minutes' => 0,
        ]]),
        $admin->id,
    );

    expect(PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', $day)->exists())->toBeTrue();
});
