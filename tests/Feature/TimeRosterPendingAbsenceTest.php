<?php

declare(strict_types=1);

use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\RequestAbsenceAction;
use App\Data\Time\DecideAbsenceRequestData;
use App\Data\Time\RequestAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\InternalTeam;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

function pendingAbsenceRosterSetup(): array
{
    Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00'));

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'is_active' => true,
    ]);
    ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'VL',
        'label' => 'Verlof',
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'is_active' => true,
    ]);
    ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'RC',
        'label' => 'Recup',
        'kind' => ShiftTypeKind::Recup,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $worker];
}

it('markeert open verlofaanvragen per dag in de roosterpayload', function () {
    [$tenant, $admin, $worker] = pendingAbsenceRosterSetup();

    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-24'),
    );

    $snapshot = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-21',
        null,
        $admin,
    );
    $payload = $snapshot->toArray();

    expect($payload['pending_absences'][$worker->id.':2026-09-23'])
        ->toMatchArray(['kind' => 'leave', 'request_id' => $request->id])
        ->and($payload['pending_absences'][$worker->id.':2026-09-24'])
        ->toMatchArray(['kind' => 'leave', 'request_id' => $request->id])
        ->and($payload['pending_absences'])->not->toHaveKey($worker->id.':2026-09-22')
        ->and($payload['pending_absence_messages']['leave'])
        ->toBe(__('time.schedule.pending_absence.leave'));
});

it('markeert open recupaanvragen apart van verlof', function () {
    [$tenant, $admin, $worker] = pendingAbsenceRosterSetup();

    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Recup, '2026-09-25', '2026-09-25'),
    );

    $pending = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-21',
        null,
        $admin,
    )->pendingAbsences;

    expect($pending[$worker->id.':2026-09-25'])
        ->toMatchArray(['kind' => 'recup', 'request_id' => $request->id]);
});

it('laat de pending-marker weg na goedkeuren', function () {
    [$tenant, $admin, $worker] = pendingAbsenceRosterSetup();

    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(true, ''),
        $admin,
    );

    expect($request->fresh()?->status)->toBe(AbsenceRequestStatus::Approved);

    $pending = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-21',
        null,
        $admin,
    )->pendingAbsences;

    expect($pending)->not->toHaveKey($worker->id.':2026-09-23');
});

it('toont geen pending-marker voor aanvragen buiten de zichtbare week', function () {
    [$tenant, $admin, $worker] = pendingAbsenceRosterSetup();

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-10-05', '2026-10-06'),
    );

    $pending = app(ListRosterWeekAction::class)->handle(
        (int) $tenant->id,
        '2026-09-21',
        null,
        $admin,
    )->pendingAbsences;

    expect($pending)->toBe([]);
});
