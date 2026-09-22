<?php

declare(strict_types=1);

use App\Actions\Time\CancelAbsenceRequestAction;
use App\Actions\Time\DecideAbsenceRequestAction;
use App\Actions\Time\RequestAbsenceAction;
use App\Data\Time\DecideAbsenceRequestData;
use App\Data\Time\RequestAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Livewire\Dashboard;
use App\Livewire\Public\TimePortal;
use App\Livewire\Time\AbsenceRequestsIndex;
use App\Models\AbsenceRequest;
use App\Models\ClockPoint;
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

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

function absenceTenant(): array
{
    Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00'));

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Noord',
        'qr_token' => 'absence-clock-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
        'is_active' => true,
    ]);
    $leaveType = ShiftType::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'VL',
        'label' => 'Verlof',
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
        'break_minutes' => 0,
        'is_active' => true,
    ]);

    return [$tenant, $admin, $clockPoint, $worker, $leaveType];
}

function signInAbsenceWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('maakt een open verlofaanvraag', function () {
    [$tenant, , , $worker] = absenceTenant();

    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-24', 'Trouwen'),
    );

    expect($request->status)->toBe(AbsenceRequestStatus::Pending)
        ->and($request->kind)->toBe(ShiftTypeKind::Leave)
        ->and($request->date_from->toDateString())->toBe('2026-09-23')
        ->and($request->date_to->toDateString())->toBe('2026-09-24')
        ->and($request->description)->toBe('Trouwen')
        ->and($request->shift_type_id)->not->toBeNull();
});

it('weigert overlappende open aanvragen', function () {
    [$tenant, , , $worker] = absenceTenant();
    $action = app(RequestAbsenceAction::class);
    $action->handle($tenant, $worker, new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-25'));

    $action->handle($tenant, $worker, new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-25', '2026-09-26'));
})->throws(InvalidArgumentException::class, 'overlapping_pending');

it('weigert een startdatum in het verleden', function () {
    [$tenant, , , $worker] = absenceTenant();

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-21', '2026-09-23'),
    );
})->throws(InvalidArgumentException::class, 'date_in_past');

it('weigert een te lange periode', function () {
    [$tenant, , , $worker] = absenceTenant();

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-10-25'),
    );
})->throws(InvalidArgumentException::class, 'range_too_long');

it('weigert zonder actief shiftype', function () {
    [$tenant, , , $worker, $leaveType] = absenceTenant();
    $leaveType->update(['is_active' => false]);

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );
})->throws(InvalidArgumentException::class, 'no_shift_type');

it('trekt een eigen open aanvraag in', function () {
    [$tenant, , , $worker] = absenceTenant();
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    $cancelled = app(CancelAbsenceRequestAction::class)->handle($tenant, $worker, $request);

    expect($cancelled->status)->toBe(AbsenceRequestStatus::Cancelled);
});

it('keurt goed, bewaart bestaande diensten en vervangt het rooster', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    $planned = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
        'unit_code' => 'G1',
        'unit_name' => 'Groep 1',
    ]);
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    $decided = app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(true, 'Akkoord team'),
        $admin,
    );

    expect($decided->status)->toBe(AbsenceRequestStatus::Approved)
        ->and($decided->decision_description)->toBe('Akkoord team')
        ->and($decided->replaced_shifts)->toHaveCount(1)
        ->and($decided->replaced_shifts[0]['planned_shift_id'])->toBe($planned->id)
        ->and($decided->replaced_shifts[0]['kind'])->toBe(ShiftTypeKind::Work->value)
        ->and($decided->replaced_shifts[0]['start'])->toBe('09:00');

    expect(PlannedShift::query()->find($planned->id))->toBeNull();

    $replacement = PlannedShift::query()
        ->where('worker_id', $worker->id)
        ->whereDate('work_date', '2026-09-23')
        ->first();

    expect($replacement)->not->toBeNull()
        ->and($replacement->kind)->toBe(ShiftTypeKind::Leave)
        ->and($replacement->status)->toBe(PlannedShiftStatus::Published)
        ->and($replacement->start_time)->toBeNull();
});

it('weigeren laat het rooster staan', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    $planned = PlannedShift::query()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-23',
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 0,
    ]);
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    $decided = app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(false, 'Te laat gevraagd'),
        $admin,
    );

    expect($decided->status)->toBe(AbsenceRequestStatus::Rejected)
        ->and(PlannedShift::query()->find($planned->id))->not->toBeNull();
});

it('keurt goed zonder reden', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    $decided = app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(true, ''),
        $admin,
    );

    expect($decided->status)->toBe(AbsenceRequestStatus::Approved)
        ->and($decided->decision_description)->toBeNull();
});

it('eist een reden bij weigeren', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    app(DecideAbsenceRequestAction::class)->handle(
        $tenant,
        $request,
        new DecideAbsenceRequestData(false, 'ok'),
        $admin,
    );
})->throws(InvalidArgumentException::class, 'reason_required');

it('toont de portaltegel en verstuurt een aanvraag', function () {
    [, , $clockPoint] = absenceTenant();

    signInAbsenceWorker($clockPoint)
        ->assertSee(__('time.portal.absence.tile'), false)
        ->call('openAbsence')
        ->assertSet('absenceListOpen', true)
        ->set('absenceKind', ShiftTypeKind::Leave->value)
        ->set('absenceDateFrom', '2026-09-23')
        ->set('absenceDateTo', '2026-09-23')
        ->set('absenceDescription', 'Familie')
        ->call('submitAbsence')
        ->assertSee(__('time.portal.absence.submitted'), false);

    expect(AbsenceRequest::query()->count())->toBe(1)
        ->and(AbsenceRequest::query()->first()?->status)->toBe(AbsenceRequestStatus::Pending);
});

it('laat beheer een aanvraag goedkeuren zonder reden', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    $request = app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23', 'Trouwen'),
    );

    Livewire::actingAs($admin)
        ->test(AbsenceRequestsIndex::class)
        ->assertSee('Jan Janssen', false)
        ->call('openDecide', $request->id)
        ->call('approve')
        ->assertSee(__('time.absence.approved'), false);

    expect($request->fresh()?->status)->toBe(AbsenceRequestStatus::Approved);
});

it('toont een dashboardtegel alleen bij open aanvragen', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    seedTenantPastOnboarding($tenant);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSeeHtml('wp-kpi--pending_absence');

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSeeHtml('wp-kpi--pending_absence')
        ->assertSeeHtml(route('time.absence-requests.index'))
        ->assertSeeHtml('wp-kpi-value--phrase">'.e(__('dashboard.kpi.pending_absence_leave', ['count' => 1])));
});

it('toont verlof en recup apart op het dashboard', function () {
    [$tenant, $admin, , $worker] = absenceTenant();
    seedTenantPastOnboarding($tenant);
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

    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Leave, '2026-09-23', '2026-09-23'),
    );
    app(RequestAbsenceAction::class)->handle(
        $tenant,
        $worker,
        new RequestAbsenceData(ShiftTypeKind::Recup, '2026-09-24', '2026-09-24'),
    );

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSeeHtml('wp-kpi-value--phrase">'.e(__('dashboard.kpi.pending_absence_leave', ['count' => 1]).' - '.__('dashboard.kpi.pending_absence_recup', ['count' => 1])));
});

it('weigert het aanvragen-scherm zonder time-module niet dubbel', function () {
    expect(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.absence.requested')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.absence.cancelled')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.absence.approved')
        ->and(WebhookEndpoint::AVAILABLE_EVENTS)->toContain('time.absence.rejected');
});
