<?php

declare(strict_types=1);

use App\Actions\Time\CopyRosterWeeksAction;
use App\Actions\Time\RestoreRosterPointAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\CopyRosterWeeksData;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterRestorePointSource;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Exceptions\RosterRestoreConflictException;
use App\Exceptions\RosterValidationException;
use App\Livewire\Time\RosterIndex;
use App\Models\AbsenceRequest;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\RosterRestorePoint;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function restorePointTenant(): array
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

function restorePointWeek(): string
{
    return now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
}

function restorePointCells(Worker $worker, string $week, array $values = []): array
{
    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = Carbon::parse($week)->addDays($i)->toDateString();
        $cells[] = ['worker_id' => $worker->id, 'date' => $date, 'raw' => $values[$date] ?? ''];
    }

    return $cells;
}

function restorePointSave(Tenant $tenant, User $admin, Worker $worker, string $week, array $values): void
{
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], restorePointCells($worker, $week, $values)),
        $admin->id,
    );
}

it('bewaart de verwijderde rij met metadata en laat de ongewijzigde dag buiten het punt', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $tuesday = Carbon::parse($week)->addDay()->toDateString();
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
    $morning = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'unit_id' => $unit->id,
        'unit_code' => 'G1',
        'unit_name' => 'Groep 1',
        'location_id' => $location->id,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);
    $tuesdayRow = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $tuesday,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '18:00',
        'end_time' => '20:00',
        'break_minutes' => 15,
        'description' => 'Dinsdag',
    ]);

    restorePointSave($tenant, $admin, $worker, $week, [
        $week => '09:00-12:00',
        $tuesday => '18:00-20:00',
    ]);

    $point = RosterRestorePoint::query()->sole();
    $row = $point->shifts[0];

    expect($point->source)->toBe(RosterRestorePointSource::Grid)
        ->and((int) $point->tenant_id)->toBe((int) $tenant->id)
        ->and((int) $point->actor_user_id)->toBe((int) $admin->id)
        ->and($point->scope)->toBe([['worker_id' => (int) $worker->id, 'date' => $week]])
        ->and($point->shifts)->toHaveCount(1)
        ->and($row['planned_shift_id'])->toBe((int) $morning->id)
        ->and($row['shift_type_id'])->toBe((int) $type->id)
        ->and($row['shift_type_code'])->toBe('V')
        ->and($row['shift_type_label'])->toBe('Vroeg')
        ->and($row['start_time'])->toBe('08:00')
        ->and($row['end_time'])->toBe('12:00')
        ->and($row['break_minutes'])->toBe(10)
        ->and($row['description'])->toBe('Ochtend')
        ->and($row['status'])->toBe(PlannedShiftStatus::Published->value)
        ->and($row['unit_id'])->toBe((int) $unit->id)
        ->and($row['unit_code'])->toBe('G1')
        ->and($row['location_id'])->toBe((int) $location->id)
        ->and(PlannedShift::find($morning->id))->toBeNull()
        ->and(PlannedShift::find($tuesdayRow->id))->not->toBeNull()
        ->and(PlannedShift::find($tuesdayRow->id)->description)->toBe('Dinsdag');
});

it('bewaart bij een dagwijziging de rij van vóór de aanpassing', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 10,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );

    $point = RosterRestorePoint::query()->sole();
    $stored = $point->shifts[0];
    $live = PlannedShift::find($row->id);

    expect($point->source)->toBe(RosterRestorePointSource::Day)
        ->and($point->scope)->toBe([['worker_id' => (int) $worker->id, 'date' => $date]])
        ->and($stored['planned_shift_id'])->toBe((int) $row->id)
        ->and($stored['end_time'])->toBe('12:00')
        ->and($stored['break_minutes'])->toBe(10)
        ->and($stored['description'])->toBe('Ochtend')
        ->and($stored['status'])->toBe(PlannedShiftStatus::Published->value)
        ->and($stored['shift_type_id'])->toBe((int) $type->id)
        ->and($live->end_time)->toBe('13:00')
        ->and($live->shift_type_id)->toBeNull()
        ->and($live->status)->toBe(PlannedShiftStatus::Draft);
});

it('schrijft geen punt voor een ongewijzigde cel', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '10:00',
        'break_minutes' => 0,
    ]);

    restorePointSave($tenant, $admin, $worker, $week, [$week => '07:00-10:00']);

    expect(RosterRestorePoint::query()->count())->toBe(0);
});

it('draait het herstelpunt terug als de opslag door overlap mislukt', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $morning = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '10:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);
    $evening = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '18:00',
        'end_time' => '20:00',
        'break_minutes' => 15,
        'description' => 'Avond',
    ]);

    expect(fn () => restorePointSave($tenant, $admin, $worker, $week, [
        $week => '0700-1000 · 0930-1200',
    ]))->toThrow(RosterValidationException::class);

    expect(RosterRestorePoint::query()->count())->toBe(0)
        ->and(PlannedShift::find($morning->id)?->description)->toBe('Ochtend')
        ->and(PlannedShift::find($evening->id)?->description)->toBe('Avond')
        ->and(PlannedShift::query()->count())->toBe(2);
});

it('toont een herstelpunt niet aan een andere tenant', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '10:00',
        'break_minutes' => 0,
        'description' => 'Ochtend',
    ]);
    restorePointSave($tenant, $admin, $worker, $week, [$week => '09:00-12:00']);

    $other = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($other->id);

    expect(RosterRestorePoint::query()->count())->toBe(0);

    Tenancy::actAs($tenant->id);

    expect(RosterRestorePoint::query()->count())->toBe(1)
        ->and((int) RosterRestorePoint::query()->sole()->tenant_id)->toBe((int) $tenant->id);
});

it('bewaart bij een weekkopie alleen de geraakte doeldatums en slaat een bezette week over', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $target = Carbon::parse($week)->addWeek()->toDateString();
    $source = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 30,
        'description' => 'Bron',
    ]);

    $report = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 1, [$worker->id]),
        $admin->id,
    );

    $point = RosterRestorePoint::query()->sole();

    expect($report['weeks'][0]['status'])->toBe(CopyRosterWeeksAction::STATUS_OK)
        ->and($point->source)->toBe(RosterRestorePointSource::Copy)
        ->and((int) $point->tenant_id)->toBe((int) $tenant->id)
        ->and($point->scope)->toBe([['worker_id' => (int) $worker->id, 'date' => $target]])
        ->and($point->shifts)->toBe([])
        ->and(PlannedShift::find($source->id)?->description)->toBe('Bron');

    $again = app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 1, [$worker->id]),
        $admin->id,
    );

    expect($again['weeks'][0]['status'])->toBe(CopyRosterWeeksAction::STATUS_OCCUPIED)
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('zet gewijzigde en verwijderde diensten terug met id, notitie, pauze en status', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
    ]);
    $morning = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'unit_id' => $unit->id,
        'unit_code' => 'G1',
        'unit_name' => 'Groep 1',
        'location_id' => $location->id,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);
    $evening = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Draft,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '18:00',
        'end_time' => '20:00',
        'break_minutes' => 15,
        'description' => 'Avond',
    ]);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $morning->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Gewijzigd',
            ],
            ['start_time' => '14:00', 'end_time' => '16:00'],
        ]),
        $admin->id,
    );

    $point = RosterRestorePoint::query()->sole();
    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true);

    $restoredMorning = PlannedShift::find($morning->id);
    $restoredEvening = PlannedShift::find($evening->id);

    expect($restoredMorning)->not->toBeNull()
        ->and($restoredMorning->end_time)->toBe('12:00')
        ->and($restoredMorning->break_minutes)->toBe(10)
        ->and($restoredMorning->description)->toBe('Ochtend')
        ->and($restoredMorning->status)->toBe(PlannedShiftStatus::Published)
        ->and((int) $restoredMorning->shift_type_id)->toBe((int) $type->id)
        ->and((int) $restoredMorning->unit_id)->toBe((int) $unit->id)
        ->and($restoredMorning->unit_code)->toBe('G1')
        ->and((int) $restoredMorning->location_id)->toBe((int) $location->id)
        ->and($restoredEvening)->not->toBeNull()
        ->and($restoredEvening->start_time)->toBe('18:00')
        ->and($restoredEvening->end_time)->toBe('20:00')
        ->and($restoredEvening->break_minutes)->toBe(15)
        ->and($restoredEvening->description)->toBe('Avond')
        ->and($restoredEvening->status)->toBe(PlannedShiftStatus::Draft)
        ->and(PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('maakt een oorspronkelijk lege dag weer leeg', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $target = Carbon::parse($week)->addWeek()->toDateString();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 0,
        'description' => 'Bron',
    ]);

    app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 1, [$worker->id]),
        $admin->id,
    );

    $point = RosterRestorePoint::query()->sole();
    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true);

    expect(PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', $target)->count())->toBe(0)
        ->and(PlannedShift::query()->whereDate('work_date', $week)->sole()->description)->toBe('Bron');
});

it('weigert een andere tenant en een beheerder zonder roosterrecht', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '10:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);
    restorePointSave($tenant, $admin, $worker, $week, [$week => '09:00-12:00']);
    $point = RosterRestorePoint::query()->sole();

    $other = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($other->id);
    $otherAdmin = User::factory()->create([
        'tenant_id' => $other->id,
        'role' => User::ROLE_ADMIN,
    ]);

    expect(fn () => app(RestoreRosterPointAction::class)->handle($other, $otherAdmin, $point))
        ->toThrow(AuthorizationException::class);

    Tenancy::actAs($tenant->id);
    $tenant->update(['time_roster_enabled' => false]);

    expect(fn () => app(RestoreRosterPointAction::class)->handle($tenant->fresh(), $admin, $point))
        ->toThrow(AuthorizationException::class)
        ->and(PlannedShift::find($row->id))->toBeNull()
        ->and(PlannedShift::query()->where('start_time', '09:00')->count())->toBe(1);
});

it('weigert het herstel als het id inmiddels op een andere dag staat', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $monday = restorePointWeek();
    $wednesday = Carbon::parse($monday)->addDays(2)->toDateString();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $monday,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $monday, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 10,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );
    $row->update(['work_date' => $wednesday]);

    $point = RosterRestorePoint::query()->sole();

    expect(fn () => app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true))
        ->toThrow(RosterValidationException::class, 'time.schedule.errors.restore_id_elsewhere');

    $kept = PlannedShift::find($row->id);

    expect($kept)->not->toBeNull()
        ->and($kept->work_date->toDateString())->toBe($wednesday)
        ->and($kept->end_time)->toBe('13:00')
        ->and(PlannedShift::query()->whereDate('work_date', $monday)->count())->toBe(0)
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('draait een mislukt herstel volledig terug', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('V', 'Vroeg', '07:00', '15:00', 30, ShiftTypeColor::Green),
        $admin->id,
    );
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 5,
                'description' => 'Gewijzigd',
            ],
        ]),
        $admin->id,
    );

    $point = RosterRestorePoint::query()->sole();
    $shifts = $point->shifts;
    $shifts[0]['shift_type_id'] = 999999;
    $point->update(['shifts' => $shifts]);

    expect(fn () => app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point->fresh(), true))
        ->toThrow(QueryException::class);

    $live = PlannedShift::find($row->id);

    expect($live)->not->toBeNull()
        ->and($live->end_time)->toBe('13:00')
        ->and($live->description)->toBe('Gewijzigd')
        ->and($live->break_minutes)->toBe(5)
        ->and($live->shift_type_id)->toBeNull()
        ->and($live->status)->toBe(PlannedShiftStatus::Draft)
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('bewaart de stand van vóór het herstel en kan dat herstel zelf terugdraaien', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $morning = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);

    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $morning->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Gewijzigd',
            ],
            ['start_time' => '14:00', 'end_time' => '16:00'],
        ]),
        $admin->id,
    );
    $added = PlannedShift::query()->where('start_time', '14:00')->sole();
    $original = RosterRestorePoint::query()->sole();

    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $original, true);

    $undo = RosterRestorePoint::query()->where('source', RosterRestorePointSource::Restore)->sole();
    $undoByStart = collect($undo->shifts)->keyBy('start_time');

    expect(PlannedShift::find($morning->id)?->end_time)->toBe('12:00')
        ->and(PlannedShift::find($added->id))->toBeNull()
        ->and($undo->scope)->toBe([['worker_id' => (int) $worker->id, 'date' => $date]])
        ->and($undo->shifts)->toHaveCount(2)
        ->and($undoByStart['08:00']['planned_shift_id'])->toBe((int) $morning->id)
        ->and($undoByStart['08:00']['end_time'])->toBe('13:00')
        ->and($undoByStart['08:00']['description'])->toBe('Gewijzigd')
        ->and($undoByStart['08:00']['break_minutes'])->toBe(0)
        ->and($undoByStart['08:00']['status'])->toBe(PlannedShiftStatus::Draft->value)
        ->and($undoByStart['14:00']['planned_shift_id'])->toBe((int) $added->id)
        ->and($undoByStart['14:00']['end_time'])->toBe('16:00');

    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $undo, true);

    expect(PlannedShift::find($morning->id)?->end_time)->toBe('13:00')
        ->and(PlannedShift::find($morning->id)?->description)->toBe('Gewijzigd')
        ->and(PlannedShift::find($added->id)?->end_time)->toBe('16:00')
        ->and(PlannedShift::query()->where('worker_id', $worker->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('kan het leegmaken van een gekopieerde dag zelf terugdraaien', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $week = restorePointWeek();
    $target = Carbon::parse($week)->addWeek()->toDateString();
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $week,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '07:00',
        'end_time' => '15:00',
        'break_minutes' => 30,
        'description' => 'Bron',
    ]);

    app(CopyRosterWeeksAction::class)->handle(
        $tenant,
        new CopyRosterWeeksData($week, $target, 1, [$worker->id]),
        $admin->id,
    );
    $copied = PlannedShift::query()->whereDate('work_date', $target)->sole();
    $emptyDay = RosterRestorePoint::query()->sole();

    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $emptyDay, true);

    $undo = RosterRestorePoint::query()->where('source', RosterRestorePointSource::Restore)->sole();

    expect(PlannedShift::query()->whereDate('work_date', $target)->count())->toBe(0)
        ->and($undo->scope)->toBe([['worker_id' => (int) $worker->id, 'date' => $target]])
        ->and($undo->shifts)->toHaveCount(1)
        ->and($undo->shifts[0]['planned_shift_id'])->toBe((int) $copied->id)
        ->and($undo->shifts[0]['start_time'])->toBe('07:00')
        ->and($undo->shifts[0]['description'])->toBeNull();

    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $undo, true);

    expect(PlannedShift::find($copied->id)?->start_time)->toBe('07:00')
        ->and(PlannedShift::query()->whereDate('work_date', $target)->count())->toBe(1)
        ->and(PlannedShift::query()->whereDate('work_date', $week)->sole()->description)->toBe('Bron');
});

it('weigert een afwijkend rooster zonder expliciete bevestiging', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 10,
        'description' => 'Ochtend',
    ]);
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Gewijzigd',
            ],
            ['start_time' => '14:00', 'end_time' => '16:00'],
        ]),
        $admin->id,
    );
    $point = RosterRestorePoint::query()->sole();
    $added = PlannedShift::query()->where('start_time', '14:00')->sole();

    try {
        app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point);
        expect(false)->toBeTrue();
    } catch (RosterRestoreConflictException $e) {
        $types = array_column($e->assessment->differences, 'type');
        expect($e->getMessage())->toBe('time.schedule.errors.restore_confirmation_required')
            ->and($e->assessment->blocked())->toBeFalse()
            ->and($e->assessment->needsConfirmation())->toBeTrue()
            ->and($types)->toContain('changed')
            ->and($types)->toContain('added');
    }

    expect(PlannedShift::find($row->id)?->end_time)->toBe('13:00')
        ->and(PlannedShift::find($added->id))->not->toBeNull()
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('laat een gelijk rooster met rust en schrijft geen extra punt', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'description' => 'Ochtend',
    ]);
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );
    $point = RosterRestorePoint::query()->sole();
    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true);

    app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point);

    expect(PlannedShift::find($row->id)?->end_time)->toBe('12:00')
        ->and(RosterRestorePoint::query()->count())->toBe(2);
});

it('blokkeert een vergrendelde dag ook met bevestiging', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $yesterday = now()->subDay()->toDateString();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $yesterday,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
    ]);
    $point = RosterRestorePoint::query()->create([
        'tenant_id' => $tenant->id,
        'actor_user_id' => $admin->id,
        'source' => RosterRestorePointSource::Day,
        'scope' => [['worker_id' => (int) $worker->id, 'date' => $yesterday]],
        'shifts' => [[
            'planned_shift_id' => (int) $row->id,
            'worker_id' => (int) $worker->id,
            'work_date' => $yesterday,
            'shift_type_id' => null,
            'kind' => ShiftTypeKind::Work->value,
            'unit_id' => null,
            'unit_code' => null,
            'unit_name' => null,
            'location_id' => null,
            'start_time' => '07:00',
            'end_time' => '11:00',
            'break_minutes' => 0,
            'description' => null,
            'status' => PlannedShiftStatus::Published->value,
        ]],
    ]);

    expect(fn () => app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true))
        ->toThrow(RosterRestoreConflictException::class, 'time.schedule.errors.locked_day');

    expect(PlannedShift::find($row->id)?->start_time)->toBe('08:00')
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('blokkeert goedgekeurd verlof en waarschuwt alleen bij een open aanvraag', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'description' => 'Ochtend',
    ]);
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );
    $point = RosterRestorePoint::query()->sole();
    $request = AbsenceRequest::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => $date,
        'date_to' => $date,
        'status' => AbsenceRequestStatus::Pending,
    ]);

    try {
        app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point);
        expect(false)->toBeTrue();
    } catch (RosterRestoreConflictException $e) {
        expect(array_column($e->assessment->warnings, 'code'))->toContain('pending_absence')
            ->and($e->assessment->blocked())->toBeFalse();
    }

    expect(PlannedShift::find($row->id)?->end_time)->toBe('13:00');

    $request->update(['status' => AbsenceRequestStatus::Approved]);

    expect(fn () => app(RestoreRosterPointAction::class)->handle($tenant, $admin, $point, true))
        ->toThrow(RosterRestoreConflictException::class, 'time.schedule.errors.restore_approved_absence');

    expect(PlannedShift::find($row->id)?->end_time)->toBe('13:00')
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('toont een herstelpunt, herstelt pas na bevestiging en kan die stap terugdraaien', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $shown = Carbon::parse($date)->format('d/m/Y');
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'description' => 'Ochtend',
    ]);
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );
    $point = RosterRestorePoint::query()->sole();

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openRestoreModal')
        ->assertSee($worker->displayName(), false)
        ->assertSee(__('time.schedule.restore.sources.day'), false)
        ->assertSee($shown, false)
        ->call('selectRestorePoint', $point->id)
        ->assertSee(__('time.schedule.restore.differences'), false)
        ->assertSee(__('time.schedule.restore.fields.end_time'), false)
        ->assertSee(__('time.schedule.restore.confirm'), false)
        ->assertDontSee(__('time.schedule.restore.success'), false);

    expect(PlannedShift::find($row->id)?->end_time)->toBe('13:00')
        ->and(RosterRestorePoint::query()->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openRestoreModal')
        ->call('selectRestorePoint', $point->id)
        ->call('confirmRestore')
        ->assertSee(__('time.schedule.restore.success'), false)
        ->assertSee(__('time.schedule.restore.undo'), false)
        ->call('undoRestore')
        ->assertSee(__('time.schedule.restore.confirm'), false)
        ->assertDontSee(__('time.schedule.restore.success'), false);

    expect(PlannedShift::find($row->id)?->end_time)->toBe('12:00')
        ->and(RosterRestorePoint::query()->where('source', RosterRestorePointSource::Restore)->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openRestoreModal')
        ->call('selectRestorePoint', RosterRestorePoint::query()->where('source', RosterRestorePointSource::Restore)->sole()->id)
        ->call('confirmRestore');

    expect(PlannedShift::find($row->id)?->end_time)->toBe('13:00')
        ->and(RosterRestorePoint::query()->count())->toBe(3);
});

it('houdt een vergrendelde dag geblokkeerd, ook als de modal de knop zou tonen', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $yesterday = now()->subDay()->toDateString();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $yesterday,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
    ]);
    $point = RosterRestorePoint::query()->create([
        'tenant_id' => $tenant->id,
        'actor_user_id' => $admin->id,
        'source' => RosterRestorePointSource::Day,
        'scope' => [['worker_id' => (int) $worker->id, 'date' => $yesterday]],
        'shifts' => [[
            'planned_shift_id' => (int) $row->id,
            'worker_id' => (int) $worker->id,
            'work_date' => $yesterday,
            'shift_type_id' => null,
            'kind' => ShiftTypeKind::Work->value,
            'unit_id' => null,
            'unit_code' => null,
            'unit_name' => null,
            'location_id' => null,
            'start_time' => '07:00',
            'end_time' => '11:00',
            'break_minutes' => 0,
            'description' => null,
            'status' => PlannedShiftStatus::Published->value,
        ]],
    ]);

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('openRestoreModal')
        ->call('selectRestorePoint', $point->id)
        ->assertSee(__('time.schedule.restore.blocks'), false)
        ->assertSee(__('time.schedule.restore.block.locked_day', [
            'date' => Carbon::parse($yesterday)->format('d/m/Y'),
            'name' => $worker->displayName(),
        ]), false)
        ->assertDontSee(__('time.schedule.restore.confirm'), false)
        ->set('restoreDetail.blocked', false)
        ->set('restoreDetail.needs_confirmation', true)
        ->call('confirmRestore')
        ->assertSee(__('time.schedule.errors.locked_day'), false)
        ->assertDontSee(__('time.schedule.restore.success'), false);

    expect(PlannedShift::find($row->id)?->start_time)->toBe('08:00')
        ->and(RosterRestorePoint::query()->count())->toBe(1);
});

it('zet de maandweergave op de maand van het herstelde punt', function () {
    [$tenant, $admin, $worker] = restorePointTenant();
    $date = restorePointWeek();
    $row = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'break_minutes' => 0,
        'description' => 'Ochtend',
    ]);
    app(SavePlannedDayAction::class)->handle(
        $tenant,
        new SavePlannedDayData((int) $worker->id, $date, [
            [
                'id' => $row->id,
                'start_time' => '08:00',
                'end_time' => '13:00',
                'break_minutes' => 0,
                'description' => 'Ochtend',
            ],
        ]),
        $admin->id,
    );
    $point = RosterRestorePoint::query()->sole();

    Livewire::actingAs($admin)
        ->test(RosterIndex::class)
        ->call('setView', 'month')
        ->assertSet('view', 'month')
        ->call('openRestoreModal')
        ->call('selectRestorePoint', $point->id)
        ->call('confirmRestore')
        ->assertSet('view', 'month')
        ->assertSet('weekStart', Carbon::parse($date)->startOfMonth()->toDateString())
        ->assertSee(__('time.schedule.restore.success'), false);

    expect(PlannedShift::find($row->id)?->end_time)->toBe('12:00');
});
