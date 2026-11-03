<?php

declare(strict_types=1);

use App\Actions\Time\ReplacePlannedShiftAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SuggestReplacementAction;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Exceptions\RosterValidationException;
use App\Models\AbsenceRequest;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Models\WorkerWeekdayMinute;
use App\Support\Tenancy;
use Carbon\Carbon;

afterEach(fn () => Tenancy::forget());

function replacementTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $sick = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('ZK', 'Ziek', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Sick),
        $admin->id,
    );

    return [$tenant, $admin, $team, $sick];
}

function replacementWorker(Tenant $tenant, InternalTeam $team, string $first = 'Werk'): Worker
{
    return Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
        'first_name' => $first,
    ]);
}

function replacementTypedBlock(
    Tenant $tenant,
    Worker $worker,
    string $date,
    ShiftType $type,
    string $start = '07:00',
    string $end = '15:00',
): PlannedShift {
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Draft,
        'kind' => $type->kind,
        'shift_type_id' => $type->id,
        'start_time' => $type->kind->isWork() ? $start : null,
        'end_time' => $type->kind->isWork() ? $end : null,
        'break_minutes' => 0,
    ]);
}

function replacementBlock(
    Tenant $tenant,
    Worker $worker,
    string $date,
    string $start = '07:00',
    string $end = '15:00',
    ?Unit $unit = null,
    ?Location $location = null,
): PlannedShift {
    return PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'unit_id' => $unit?->id,
        'unit_code' => $unit?->roster_code,
        'unit_name' => $unit?->name,
        'location_id' => $location?->id,
        'start_time' => $start,
        'end_time' => $end,
        'break_minutes' => 0,
    ]);
}

it('filtert kandidaten: overlap, afwezigheid, patroon-nuldag en locatie', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $original = replacementWorker($tenant, $team, 'Origineel');

    $block = replacementBlock($tenant, $original, $date);

    $free = replacementWorker($tenant, $team, 'Vrij');
    $busy = replacementWorker($tenant, $team, 'Bezet');
    replacementBlock($tenant, $busy, $date, '14:00', '20:00');

    $sickWorker = replacementWorker($tenant, $team, 'Ziek');
    PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $sickWorker->id,
        'work_date' => $date,
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Sick,
        'shift_type_id' => $sick->id,
        'break_minutes' => 0,
    ]);

    $leaveWorker = replacementWorker($tenant, $team, 'Verlof');
    AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $leaveWorker->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => $date,
        'date_to' => $date,
        'status' => AbsenceRequestStatus::Approved,
    ]);

    $pendingWorker = replacementWorker($tenant, $team, 'Aangevraagd');
    AbsenceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $pendingWorker->id,
        'kind' => ShiftTypeKind::Leave,
        'date_from' => $date,
        'date_to' => $date,
        'status' => AbsenceRequestStatus::Pending,
    ]);

    // Expliciete werkregeling met 0 minuten op de weekdag van het blok:
    // enkel zo'n rij sluit de worker uit — zonder patroon valt niemand af.
    $offDayWorker = replacementWorker($tenant, $team, 'Nuldag');
    $offDayWorker->update(['weekly_contract_minutes' => 2280]);
    $blockWeekday = (int) Carbon::parse($date)->dayOfWeekIso;
    foreach (range(1, 7) as $weekday) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $offDayWorker->id,
            'weekday' => $weekday,
            'minutes' => $weekday === $blockWeekday ? 0 : ($weekday <= 5 ? 570 : 0),
        ]);
    }

    $restrictedWorker = replacementWorker($tenant, $team, 'Beperkt');
    $restrictedWorker->locations()->sync([Location::factory()->create(['tenant_id' => $tenant->id])->id]);
    $block->update(['location_id' => Location::factory()->create(['tenant_id' => $tenant->id])->id]);

    $reserveType = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('RES', 'Reserve', '07:00', '15:00', 0, ShiftTypeColor::Green, true, ShiftTypeKind::Work, true),
        $admin->id,
    );
    $leaveType = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Leave, true),
        $admin->id,
    );
    $reserve = replacementWorker($tenant, $team, 'Reserve');
    replacementTypedBlock($tenant, $reserve, $date, $reserveType);
    $onLeave = replacementWorker($tenant, $team, 'Poolverlof');
    replacementTypedBlock($tenant, $onLeave, $date, $leaveType);

    $candidates = app(SuggestReplacementAction::class)->handle($tenant, [$block->id]);
    $ids = array_column($candidates, 'worker_id');

    expect($ids)->toContain((int) $reserve->id)
        ->and($ids)->toContain((int) $onLeave->id)
        ->and($ids)->not->toContain((int) $free->id)
        ->and($ids)->not->toContain((int) $original->id)
        ->and($ids)->not->toContain((int) $busy->id)
        ->and($ids)->not->toContain((int) $sickWorker->id)
        ->and($ids)->not->toContain((int) $leaveWorker->id)
        ->and($ids)->not->toContain((int) $pendingWorker->id)
        ->and($ids)->not->toContain((int) $offDayWorker->id)
        ->and($ids)->not->toContain((int) $restrictedWorker->id);
});

it('houdt een kandidaat met een expliciet patroon zonder nulminuten op die weekdag wel voor', function () {
    [$tenant, , $team] = replacementTenant();
    $tenant->update(['time_replacement_include_unplanned' => true]);
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $original = replacementWorker($tenant, $team, 'Origineel');
    $block = replacementBlock($tenant, $original, $date);

    // Expliciete werkregeling, maar mét minuten op de blok-weekdag (ma = 456):
    // alleen een rij met 0 minuten mag uitsluiten.
    $patternWorker = replacementWorker($tenant, $team, 'Patroon');
    $patternWorker->update(['weekly_contract_minutes' => 2280]);
    foreach (range(1, 7) as $weekday) {
        WorkerWeekdayMinute::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $patternWorker->id,
            'weekday' => $weekday,
            'minutes' => $weekday <= 5 ? 456 : 0,
        ]);
    }

    $ids = array_column(
        app(SuggestReplacementAction::class)->handle($tenant, [$block->id]),
        'worker_id',
    );

    expect($ids)->toContain((int) $patternWorker->id);
});

it('rangschikt: zelfde unit, dan zelfde locatie, dan de rest', function () {
    [$tenant, $admin, $team] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $reserveType = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('RES', 'Reserve', '07:00', '15:00', 0, ShiftTypeColor::Green, true, ShiftTypeKind::Work, true),
        $admin->id,
    );
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'G1',
    ]);
    $original = replacementWorker($tenant, $team, 'Origineel');
    $block = replacementBlock($tenant, $original, $date, '07:00', '15:00', $unit, $location);

    $sameUnit = replacementWorker($tenant, $team, 'UnitWorker');
    $sameUnit->update(['default_unit_id' => $unit->id]);
    replacementTypedBlock($tenant, $sameUnit, $date, $reserveType);

    $sameLocation = replacementWorker($tenant, $team, 'LocatieWorker');
    $sameLocation->locations()->sync([$location->id]);
    replacementTypedBlock($tenant, $sameLocation, $date, $reserveType);

    $other = replacementWorker($tenant, $team, 'Andere');
    replacementTypedBlock($tenant, $other, $date, $reserveType);

    $candidates = app(SuggestReplacementAction::class)->handle($tenant, [$block->id]);
    $order = array_column($candidates, 'worker_id');

    expect($order)->toBe([(int) $sameUnit->id, (int) $sameLocation->id, (int) $other->id]);
});

it('zet een blok om naar afwezigheid en maakt een spiegelblok voor de vervanger', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $tenant->update(['time_replacement_include_unplanned' => true]);
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'G1',
    ]);

    $original = replacementWorker($tenant, $team, 'Origineel');
    $replacement = replacementWorker($tenant, $team, 'Vervanger');
    $block = replacementBlock($tenant, $original, $date, '18:00', '20:00', $unit, $location);
    $blockId = (int) $block->id;

    $result = app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [$blockId],
        (int) $replacement->id,
        (int) $sick->id,
        $admin->id,
    );

    expect($result['converted'])->toBe(1)
        ->and($result['created'])->toHaveCount(1);

    $block->refresh();
    expect($block->id)->toBe($blockId)
        ->and($block->kind)->toBe(ShiftTypeKind::Sick)
        ->and((int) $block->shift_type_id)->toBe((int) $sick->id)
        ->and($block->unit_id)->toBeNull()
        ->and($block->unit_code)->toBeNull();

    $mirror = $result['created'][0];
    expect($mirror->worker_id)->toBe((int) $replacement->id)
        ->and($mirror->kind)->toBe(ShiftTypeKind::Work)
        ->and($mirror->start_time)->toBe('18:00')
        ->and($mirror->end_time)->toBe('20:00')
        ->and((int) $mirror->unit_id)->toBe((int) $unit->id)
        ->and((int) $mirror->location_id)->toBe((int) $location->id)
        ->and($mirror->status)->toBe(PlannedShiftStatus::Published);

    // Published dag gewijzigd → RosterChanged voor beide workers.
    foreach ([$original, $replacement] as $worker) {
        expect(WorkerNotification::where('worker_id', $worker->id)
            ->where('type', 'roster_changed')
            ->where('reference_id', $date)
            ->exists())->toBeTrue();
    }
});

it('zet bij een volledig afwezige dag alle blokken om', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $tenant->update(['time_replacement_include_unplanned' => true]);
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $original = replacementWorker($tenant, $team, 'Origineel');
    $replacement = replacementWorker($tenant, $team, 'Vervanger');

    $a = replacementBlock($tenant, $original, $date, '07:00', '10:00');
    $b = replacementBlock($tenant, $original, $date, '18:00', '20:00');

    $result = app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [$a->id, $b->id],
        (int) $replacement->id,
        (int) $sick->id,
        $admin->id,
    );

    expect($result['converted'])->toBe(2)
        ->and($result['created'])->toHaveCount(2)
        ->and(PlannedShift::where('worker_id', $original->id)->where('kind', ShiftTypeKind::Work->value)->whereDate('work_date', $date)->count())->toBe(0)
        ->and(PlannedShift::where('worker_id', $replacement->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('weigert een kandidaat die niet in de suggestielijst staat', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $original = replacementWorker($tenant, $team, 'Origineel');
    $busy = replacementWorker($tenant, $team, 'Bezet');
    replacementBlock($tenant, $busy, $date, '10:00', '20:00');

    $block = replacementBlock($tenant, $original, $date, '07:00', '15:00');

    expect(fn () => app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [$block->id],
        (int) $busy->id,
        (int) $sick->id,
        $admin->id,
    ))->toThrow(RosterValidationException::class, 'replacement_not_eligible');
});

it('toont uitvoerders zonder planning alleen als de tenant dat aanzet', function () {
    [$tenant, , $team] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $original = replacementWorker($tenant, $team, 'Origineel');
    $block = replacementBlock($tenant, $original, $date);
    $free = replacementWorker($tenant, $team, 'Vrij');

    $without = array_column(app(SuggestReplacementAction::class)->handle($tenant, [$block->id]), 'worker_id');
    expect($without)->not->toContain((int) $free->id);

    $tenant->update(['time_replacement_include_unplanned' => true]);

    $with = array_column(app(SuggestReplacementAction::class)->handle($tenant, [$block->id]), 'worker_id');
    expect($with)->toContain((int) $free->id);
});

it('houdt een overlappend reserveblok als kandidaat en verwijdert het bij vervangen', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $reserveType = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('RES', 'Reserve', '07:00', '17:00', 0, ShiftTypeColor::Green, true, ShiftTypeKind::Work, true),
        $admin->id,
    );
    $original = replacementWorker($tenant, $team, 'Origineel');
    $reserve = replacementWorker($tenant, $team, 'Reserve');
    $block = replacementBlock($tenant, $original, $date, '08:00', '16:00');
    $pool = replacementTypedBlock($tenant, $reserve, $date, $reserveType, '07:00', '17:00');

    $ids = array_column(app(SuggestReplacementAction::class)->handle($tenant, [$block->id]), 'worker_id');
    expect($ids)->toContain((int) $reserve->id);

    app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [(int) $block->id],
        (int) $reserve->id,
        (int) $sick->id,
        $admin->id,
    );

    expect(PlannedShift::query()->whereKey($pool->id)->exists())->toBeFalse()
        ->and(PlannedShift::query()->where('worker_id', $reserve->id)->where('kind', ShiftTypeKind::Work->value)->whereDate('work_date', $date)->count())->toBe(1);
});

it('laat een pool-werkblok staan dat de nieuwe dienst niet raakt', function () {
    [$tenant, $admin, $team, $sick] = replacementTenant();
    $date = now()->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    $reserveType = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('RES', 'Reserve', '16:00', '20:00', 0, ShiftTypeColor::Green, true, ShiftTypeKind::Work, true),
        $admin->id,
    );
    $original = replacementWorker($tenant, $team, 'Origineel');
    $reserve = replacementWorker($tenant, $team, 'Reserve');
    $block = replacementBlock($tenant, $original, $date, '08:00', '12:00');
    $evening = replacementTypedBlock($tenant, $reserve, $date, $reserveType, '16:00', '20:00');

    app(ReplacePlannedShiftAction::class)->handle(
        $tenant,
        [(int) $block->id],
        (int) $reserve->id,
        (int) $sick->id,
        $admin->id,
    );

    expect(PlannedShift::query()->whereKey($evening->id)->exists())->toBeTrue()
        ->and(PlannedShift::query()->where('worker_id', $reserve->id)->whereDate('work_date', $date)->count())->toBe(2);
});

it('zet een ziektetype nooit in de vervangingspool', function () {
    [$tenant, $admin] = replacementTenant();
    $type = app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('ZK2', 'Ziek extra', null, null, 0, ShiftTypeColor::Pink, true, ShiftTypeKind::Sick, true),
        $admin->id,
    );

    expect($type->is_replacement_pool)->toBeFalse();
});
