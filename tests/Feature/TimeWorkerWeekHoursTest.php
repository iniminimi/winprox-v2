<?php

declare(strict_types=1);

use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SummarizeWorkerWeekHoursAction;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\WorkerWeekHours;
use App\Enums\ClockSource;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkShiftStatus;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Team\UpdateWorkerRequest;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

afterEach(fn () => Tenancy::forget());

function weekHoursTenant(): array
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

function weekBlock(
    Tenant $tenant,
    Worker $worker,
    string $date,
    ShiftTypeKind $kind,
    ?string $start,
    ?string $end,
    int $break = 0,
    PlannedShiftStatus $status = PlannedShiftStatus::Draft,
): PlannedShift {
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'kind' => $kind,
        'start_time' => $start,
        'end_time' => $end,
        'break_minutes' => $break,
        'status' => $status,
    ]);
}

function weekPunch(Tenant $tenant, Worker $worker, string $in, ?string $out, int $break = 0): WorkShift
{
    return WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => $out === null ? WorkShiftStatus::Open : WorkShiftStatus::Closed,
        'clock_in_at' => Carbon::parse($in, 'Europe/Brussels'),
        'clock_out_at' => $out === null ? null : Carbon::parse($out, 'Europe/Brussels'),
        'clock_out_source' => $out === null ? null : ClockSource::ClockPointQr,
        'total_break_minutes' => $break,
    ]);
}

function weekSummary(Tenant $tenant, Worker $worker, string $weekStart): WorkerWeekHours
{
    $rows = app(SummarizeWorkerWeekHoursAction::class)->handle(
        (int) $tenant->id,
        [(int) $worker->id],
        $weekStart,
    );

    expect($rows)->toHaveKey((int) $worker->id);

    return $rows[(int) $worker->id];
}

it('zet 38 uur als standaard en laat een lege norm toe', function () {
    [$tenant, $admin, $team] = weekHoursTenant();

    $created = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Nieuw',
        'last_name' => 'Persoon',
    ], $admin->id);

    expect($created->weekly_contract_minutes)->toBe(Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES);

    $cleared = app(CreateWorkerAction::class)->handle($team, [
        'first_name' => 'Zonder',
        'last_name' => 'Norm',
        'weekly_contract_minutes' => null,
    ], $admin->id);

    expect($cleared->weekly_contract_minutes)->toBeNull();
});

it('bewaart de weeknorm als minuten, inclusief 0 en null', function () {
    [$tenant, $admin, , $worker] = weekHoursTenant();
    $update = app(UpdateWorkerAction::class);
    $base = [
        'first_name' => $worker->first_name,
        'last_name' => $worker->last_name,
    ];

    $update->handle($worker, $base + ['weekly_contract_minutes' => 2280], $admin->id);
    expect($worker->fresh()->weekly_contract_minutes)->toBe(2280);

    $update->handle($worker, $base + ['weekly_contract_minutes' => 0], $admin->id);
    expect($worker->fresh()->weekly_contract_minutes)->toBe(0);

    $update->handle($worker, $base + ['weekly_contract_minutes' => null], $admin->id);
    expect($worker->fresh()->weekly_contract_minutes)->toBeNull();

    $rules = ['weekly_contract_minutes' => UpdateWorkerRequest::weeklyContractMinuteRules()];
    expect(Validator::make(['weekly_contract_minutes' => 10080], $rules)->fails())->toBeFalse()
        ->and(Validator::make(['weekly_contract_minutes' => 0], $rules)->fails())->toBeFalse()
        ->and(Validator::make(['weekly_contract_minutes' => null], $rules)->fails())->toBeFalse()
        ->and(Validator::make(['weekly_contract_minutes' => 10081], $rules)->fails())->toBeTrue();
});

it('telt meerdere werkblokken, concept en gepubliceerd, en een pauze', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 2280]);

    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '08:00', '12:00', 0, PlannedShiftStatus::Draft);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '13:00', '17:00', 30, PlannedShiftStatus::Published);

    $summary = weekSummary($tenant, $worker, '2026-10-13');

    expect($summary->plannedMinutes)->toBe((4 * 60) + (4 * 60 - 30))
        ->and($summary->differenceMinutes())->toBe($summary->plannedMinutes - 2280)
        ->and($summary->contractMinutes)->toBe(2280);
});

it('telt een verborgen zaterdag mee', function () {
    [$tenant, $admin, $team, $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-17', ShiftTypeKind::Work, '08:00', '12:00');

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-10-13',
        (int) $team->id,
        $admin,
        'week',
        false,
    );
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);

    expect($snapshot->dates)->not->toContain('2026-10-17')
        ->and($row['week_hours']['planned_minutes'])->toBe(4 * 60);
});

it('slaat ongeldige werkblokken over en telt ze als onvolledig', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '08:00', null);
    weekBlock($tenant, $worker, '2026-10-14', ShiftTypeKind::Work, '08:00', '09:00', 90);
    weekBlock($tenant, $worker, '2026-10-15', ShiftTypeKind::Work, '22:00', '06:00');
    weekBlock($tenant, $worker, '2026-10-16', ShiftTypeKind::Work, '08:00', '09:00', 60);

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->plannedMinutes)->toBe(0)
        ->and($summary->incompleteBlocks)->toBe(3);
});

it('telt gesloten diensten via netWorkMinutes en laat open diensten weg', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekPunch($tenant, $worker, '2026-10-13 08:00:00', '2026-10-13 16:30:00', 30);
    weekPunch($tenant, $worker, '2026-10-14 08:00:00', null);

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->workedMinutes)->toBe(8 * 60)
        ->and($summary->registrationIncomplete)->toBeTrue();
});

it('geeft een open dienst van vóór de week een signaal zonder minuten', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekPunch($tenant, $worker, '2026-10-11 08:00:00', null);

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->workedMinutes)->toBe(0)
        ->and($summary->registrationIncomplete)->toBeTrue();
});

it('legt een dienst over middernacht volledig op de inklokdatum', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekPunch($tenant, $worker, '2026-10-18 22:00:00', '2026-10-19 06:00:00');

    $sundayWeek = weekSummary($tenant, $worker, '2026-10-12');
    $mondayWeek = weekSummary($tenant, $worker, '2026-10-19');

    expect($sundayWeek->workedMinutes)->toBe(8 * 60)
        ->and($mondayWeek->workedMinutes)->toBe(0);
});

it('toont een hele afwezigheidsdag zonder verzonnen uren', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Leave, null, null);
    weekBlock($tenant, $worker, '2026-10-14', ShiftTypeKind::Leave, null, null);

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->plannedMinutes)->toBe(0)
        ->and($summary->absences['leave']['days'])->toBe(2)
        ->and($summary->absences['leave']['minutes'])->toBe(0)
        ->and($summary->incompleteBlocks)->toBe(0);
});

it('telt een deelafwezigheid met opgeslagen tijden en pauze niet als gepland werk', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Sick, '08:00', '12:00', 30);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '13:00', '17:00');

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->plannedMinutes)->toBe(4 * 60)
        ->and($summary->absences['sick']['days'])->toBe(0)
        ->and($summary->absences['sick']['minutes'])->toBe((4 * 60) - 30);
});

it('houdt een hele dag op 1 naast een halfgevulde rij van hetzelfde type', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Leave, null, null);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Leave, '08:00', null);

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->absences['leave']['days'])->toBe(1)
        ->and($summary->absences['leave']['minutes'])->toBe(0)
        ->and($summary->incompleteBlocks)->toBe(1)
        ->and($summary->plannedMinutes)->toBe(0);
});

it('telt een getimede rij niet nog eens op een datum die al een hele dag is', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Recup, null, null);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Recup, '08:00', '10:00');
    weekBlock($tenant, $worker, '2026-10-14', ShiftTypeKind::Recup, '08:00', '10:00');

    $summary = weekSummary($tenant, $worker, '2026-10-12');

    expect($summary->absences['recup']['days'])->toBe(1)
        ->and($summary->absences['recup']['minutes'])->toBe(2 * 60)
        ->and($summary->incompleteBlocks)->toBe(0);
});

it('geeft geen verschil zonder weeknorm en wel bij 0, positief en negatief', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '08:00', '12:00');

    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBeNull();

    $worker->update(['weekly_contract_minutes' => 0]);
    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBe(4 * 60);

    $worker->update(['weekly_contract_minutes' => 180]);
    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBe(60);

    $worker->update(['weekly_contract_minutes' => 300]);
    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBe(-60);
});

it('gebruikt de actuele norm ook voor een oudere week', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '08:00', '16:00');
    $worker->update(['weekly_contract_minutes' => 2280]);

    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBe((8 * 60) - 2280);

    $worker->update(['weekly_contract_minutes' => 1800]);

    expect(weekSummary($tenant, $worker, '2026-10-12')->differenceMinutes())->toBe((8 * 60) - 1800);
});

it('houdt de weekgrens in lokale tijd, ook rond zomer- en wintertijd', function () {
    [$tenant, , , $worker] = weekHoursTenant();

    $winterStart = Carbon::parse('2026-10-19', 'Europe/Brussels')->startOfDay();
    expect($winterStart->copy()->addWeek()->getTimestamp() - $winterStart->getTimestamp())->toBe(169 * 3600);

    $summerStart = Carbon::parse('2026-03-23', 'Europe/Brussels')->startOfDay();
    expect($summerStart->copy()->addWeek()->getTimestamp() - $summerStart->getTimestamp())->toBe(167 * 3600);

    weekPunch($tenant, $worker, '2026-10-26 00:30:00', '2026-10-26 08:30:00');
    weekPunch($tenant, $worker, '2026-03-30 00:30:00', '2026-03-30 08:30:00');

    expect(weekSummary($tenant, $worker, '2026-10-19')->workedMinutes)->toBe(0)
        ->and(weekSummary($tenant, $worker, '2026-10-26')->workedMinutes)->toBe(8 * 60)
        ->and(weekSummary($tenant, $worker, '2026-03-23')->workedMinutes)->toBe(0)
        ->and(weekSummary($tenant, $worker, '2026-03-30')->workedMinutes)->toBe(8 * 60);
});

it('laat de norm alleen door aan admin en medewerker van dezelfde tenant', function () {
    [$tenant, $admin, $team, $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 2220]);
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);
    $super = User::factory()->superuser()->create();
    $list = app(ListRosterWeekAction::class);
    $forAdminSnapshot = $list->handle((int) $tenant->id, '2026-10-13', (int) $team->id, $admin);
    $forEmployeeSnapshot = $list->handle((int) $tenant->id, '2026-10-13', (int) $team->id, $employee);
    $forSuperSnapshot = $list->handle((int) $tenant->id, '2026-10-13', (int) $team->id, $super);
    $forAdmin = collect($forAdminSnapshot->workers)->firstWhere('id', $worker->id);
    $forEmployee = collect($forEmployeeSnapshot->workers)->firstWhere('id', $worker->id);
    $forSuper = collect($forSuperSnapshot->workers)->firstWhere('id', $worker->id);
    $superKeys = array_column($forSuperSnapshot->hoursColumns, 'key');

    expect($forAdmin['week_hours']['contract_minutes'])->toBe(2220)
        ->and($forAdmin['week_hours']['difference_minutes'])->toBe(-2220)
        ->and($forEmployee['week_hours']['contract_minutes'])->toBe(2220)
        ->and(array_column($forAdminSnapshot->hoursColumns, 'key'))->toContain('norm')
        ->and($forSuper['week_hours'])->not->toHaveKey('contract_minutes')
        ->and($forSuper['week_hours'])->not->toHaveKey('difference_minutes')
        ->and($superKeys)->not->toContain('norm')
        ->and($superKeys)->not->toContain('difference')
        ->and($forSuper['week_hours']['figures'])->toHaveCount(count($superKeys))
        ->and(json_encode($forSuper['week_hours']))->not->toContain('2220');
});

it('zet de uren in vaste cijferkolommen', function () {
    [$tenant, $admin, $team, $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 120]);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '13:00', '17:00');
    weekBlock($tenant, $worker, '2026-10-14', ShiftTypeKind::Leave, null, null);
    weekBlock($tenant, $worker, '2026-10-15', ShiftTypeKind::Sick, '08:00', '10:00');
    weekBlock($tenant, $worker, '2026-10-16', ShiftTypeKind::Leave, '08:00', null);
    weekPunch($tenant, $worker, '2026-10-12 08:00:00', null);

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-10-12',
        (int) $team->id,
        $admin,
    );
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);
    $keys = array_column($snapshot->hoursColumns, 'key');
    $figure = function (string $key) use ($keys, $row): ?array {
        return $row['week_hours']['figures'][array_search($key, $keys, true)];
    };
    $hour = __('time.duration.hour_short');

    expect($keys)->toBe(['norm', 'difference', 'planned', 'worked', 'leave', 'recup', 'sick', 'attention'])
        ->and($figure('norm')['value'])->toBe('2'.$hour)
        ->and($figure('difference')['value'])->toBe('+2'.$hour)
        ->and($figure('planned')['value'])->toBe('4'.$hour)
        ->and($figure('worked')['value'])->toBe('0')
        ->and($figure('leave')['value'])->toBe('1')
        ->and($figure('recup'))->toBeNull()
        ->and($figure('sick')['value'])->toBe('2'.$hour)
        ->and($figure('attention')['value'])->toBe('1 · !')
        ->and($figure('attention')['title'])->toContain(__('time.schedule.week_hours.registration_incomplete'));
});

it('toont minuten compact in de cijferkolommen (15u32, geen 15u32min)', function () {
    [$tenant, $admin, $team, $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 125]);
    weekBlock($tenant, $worker, '2026-10-13', ShiftTypeKind::Work, '08:00', '23:32');

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-10-12',
        (int) $team->id,
        $admin,
    );
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);
    $keys = array_column($snapshot->hoursColumns, 'key');
    $figure = fn (string $key): ?array => $row['week_hours']['figures'][array_search($key, $keys, true)];
    $hour = __('time.duration.hour_short');

    expect($figure('norm')['value'])->toBe('2'.$hour.'05')
        ->and($figure('planned')['value'])->toBe('15'.$hour.'32')
        ->and($figure('difference')['value'])->toBe('+13'.$hour.'27')
        ->and($figure('planned')['value'])->not->toContain(__('time.duration.minute_short'));
});

it('mengt geen uren van een andere tenant', function () {
    [$tenant, , , $worker] = weekHoursTenant();
    $other = Tenant::factory()->create(['has_time_module' => true]);
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $other->id]);
    $otherWorker = Worker::factory()->create([
        'tenant_id' => $other->id,
        'internal_team_id' => $otherTeam->id,
        'weekly_contract_minutes' => 1140,
    ]);
    Tenancy::actAs($other->id);
    weekBlock($other, $otherWorker, '2026-10-13', ShiftTypeKind::Work, '08:00', '16:00');
    Tenancy::actAs($tenant->id);

    $rows = app(SummarizeWorkerWeekHoursAction::class)->handle(
        (int) $tenant->id,
        [(int) $worker->id, (int) $otherWorker->id],
        '2026-10-12',
    );

    expect($rows)->toHaveKey((int) $worker->id)
        ->and($rows)->not->toHaveKey((int) $otherWorker->id)
        ->and($rows[(int) $worker->id]->plannedMinutes)->toBe(0)
        ->and($rows[(int) $worker->id]->contractMinutes)->toBeNull();
});

it('blokkeert opslaan en publiceren niet bij een afwijking van de norm', function () {
    [$tenant, $admin, , $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 2280]);
    $day = Carbon::now('Europe/Brussels')->addWeeks(2)->startOfWeek(Carbon::MONDAY)->toDateString();

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $day, [[
            'start_time' => '08:00',
            'end_time' => '12:00',
            'break_minutes' => 0,
        ]]),
        $admin->id,
    );

    $published = app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($day, [(int) $worker->id]),
        $admin->id,
    );

    expect($published)->toBe(1)
        ->and(PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', $day)->value('status'))
        ->toBe(PlannedShiftStatus::Published);
});

it('laat de historische vergrendeling staan en schrijft het overzicht niets', function () {
    [$tenant, $admin, , $worker] = weekHoursTenant();
    $past = Carbon::now('Europe/Brussels')->subDay()->toDateString();

    expect(fn () => app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $past, [[
            'start_time' => '08:00',
            'end_time' => '12:00',
            'break_minutes' => 0,
        ]]),
        $admin->id,
    ))->toThrow(RosterValidationException::class);

    expect(PlannedShift::query()->count())->toBe(0);

    weekSummary($tenant, $worker, $past);

    expect(PlannedShift::query()->count())->toBe(0);
});

it('zet geen weeknorm op de maandweergave', function () {
    [$tenant, $admin, $team, $worker] = weekHoursTenant();
    $worker->update(['weekly_contract_minutes' => 2280]);

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-10-13',
        (int) $team->id,
        $admin,
        'month',
    );
    $row = collect($snapshot->workers)->firstWhere('id', $worker->id);

    expect($snapshot->period)->toBe('month')
        ->and($row)->not->toHaveKey('week_hours');
});
