<?php

use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\StartTaskAction;
use App\Actions\Time\AutoCloseStaleWorkShiftsAction;
use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Enums\TimePresenceStatusFilter;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Livewire\Public\TimePortal;
use App\Livewire\Time\ClockPointsIndex;
use App\Livewire\Time\PresenceIndex;
use App\Livewire\Time\ShiftsIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Models\WorkShiftTaskLog;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function timeTenantWithAdmin(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    return [$tenant, $admin];
}

it('laat een admin het time-aanwezigheidsscherm openen', function () {
    [$tenant, $admin] = timeTenantWithAdmin();

    $this->actingAs($admin)
        ->get(route('time.presence.index'))
        ->assertOk();
});

it('laadt afwezige werknemers in board-dashboard', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $clockedIn = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    app(ClockInAction::class)->handle($clockedIn, $clockPoint);

    $teamIds = InternalTeam::query()->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle(
        $tenant->id,
        null,
        null,
        null,
        '',
        TimePresenceStatusFilter::All,
        $teamIds,
        true,
    );

    expect($dashboard->kpis->notClockedIn)->toBe(1)
        ->and($dashboard->teamBuckets->sum('absentCount'))->toBe(1)
        ->and($dashboard->teamBuckets->flatMap(fn ($b) => $b->absentWorkers)->count())->toBe(1);
});

it('toont afwezige werknemers in board-weergave', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Onderhoud']);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $clockedIn = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Aanwezig',
    ]);
    $absent = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Piet',
        'last_name' => 'Afwezig',
    ]);
    app(ClockInAction::class)->handle($clockedIn, $clockPoint);

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertSet('viewMode', 'board')
        ->assertSee('Jan Aanwezig', false)
        ->assertSee('Piet Afwezig', false);
});

it('wisselt tussen board-, teams-, teamkaarten- en locatie-weergave', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Techniek']);
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hoofdkantoor']);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    app(ClockInAction::class)->handle($worker, $clockPoint);

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertSet('viewMode', 'board')
        ->assertSee('wp-time-presence-board', false)
        ->assertSee(__('time.presence.force_close'), false)
        ->call('setViewMode', 'teams')
        ->assertSet('viewMode', 'teams')
        ->assertSee('wp-time-presence-teams', false)
        ->call('setViewMode', 'cards')
        ->assertSet('viewMode', 'cards')
        ->assertSee('wp-time-presence-card-grid', false)
        ->assertSee(__('time.presence.view_team'), false)
        ->call('setViewMode', 'locations')
        ->assertSet('viewMode', 'locations')
        ->assertSee('Hoofdkantoor', false);
});

it('laat een admin een urenstaat afdrukken', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(ClockOutAction::class)->handle($worker, $clockPoint);

    $this->actingAs($admin)
        ->get(route('time.shifts.print', [
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSee(__('time.print.title'), false);
});

it('klokt in met lockForUpdate en weigert dubbele open shift', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $clockIn = app(ClockInAction::class);
    $shift = $clockIn->handle($worker, $clockPoint);
    expect($shift->status)->toBe(WorkShiftStatus::Open);

    expect(fn () => $clockIn->handle($worker->fresh(), $clockPoint))
        ->toThrow(InvalidArgumentException::class, 'shift_already_open');
});

it('sluit een shift via uitklokken en berekent pauzeminuten', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->breaks()->create([
        'tenant_id' => $tenant->id,
        'started_at' => now()->subMinutes(30),
        'ended_at' => now()->subMinutes(15),
        'break_type' => \App\Enums\BreakType::Break,
    ]);

    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);
    expect($closed->status)->toBe(WorkShiftStatus::Closed)
        ->and($closed->total_break_minutes)->toBe(15);
});

it('laat een admin een open shift geforceerd sluiten met reden en auditlog', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->call('openForceClose', $shift->id)
        ->set('forceCloseReason', 'Vergeten uit te klokken')
        ->call('confirmForceClose')
        ->assertHasNoErrors();

    expect($shift->fresh()->status)->toBe(WorkShiftStatus::ForceClosed)
        ->and($shift->fresh()->clock_out_at)->not->toBeNull();

    expect(DB::table('audit_logs')
        ->where('action', 'work_shift.force_closed')
        ->where('model_id', $shift->id)
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});

it('toont het time-portaal en laat een worker inklokken na icoonbevestiging', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'time-demo-token',
    ]);

    Livewire::test(TimePortal::class, ['token' => 'time-demo-token'])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSet('flashMessage', __('time.portal.clocked_in'));

    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});

it('blokkeert het time-portaal bij inactieve tenant', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    Tenancy::actAs($tenant->id);

    ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'inactive-clock',
    ]);

    Livewire::test(TimePortal::class, ['token' => 'inactive-clock'])
        ->assertSet('inactiveReasonKey', 'portal.inactive.tenant_inactive');
});

it('laat een admin een clock point aanmaken', function () {
    [$tenant, $admin] = timeTenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(ClockPointsIndex::class)
        ->call('openCreate')
        ->set('name', 'Hoofdingang')
        ->call('save')
        ->assertHasNoErrors();

    expect(ClockPoint::where('name', 'Hoofdingang')->exists())->toBeTrue();
});

it('isoleert time-data per tenant', function () {
    $tenantA = Tenant::factory()->create(['has_time_module' => true]);
    $tenantB = Tenant::factory()->create(['has_time_module' => true]);

    Tenancy::actAs($tenantA->id);
    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenantA->id]);
    $workerA = Worker::factory()->create([
        'tenant_id' => $tenantA->id,
        'internal_team_id' => $teamA->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockA = ClockPoint::factory()->create(['tenant_id' => $tenantA->id]);
    app(ClockInAction::class)->handle($workerA, $clockA);

    Tenancy::actAs($tenantB->id);
    expect(WorkShift::query()->count())->toBe(0);
});

it('sluit vergeten open shifts automatisch na drempel', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $staleShift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(20),
    ]);

    $recentShift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
            'field_icon_slug' => 'star',
        ])->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(2),
    ]);

    $closed = app(AutoCloseStaleWorkShiftsAction::class)->handle(16);

    expect($closed)->toBe(1)
        ->and($staleShift->fresh()->status)->toBe(WorkShiftStatus::ForceClosed)
        ->and($staleShift->fresh()->clock_out_source)->toBe(ClockSource::Auto)
        ->and($staleShift->fresh()->clock_out_at)->not->toBeNull()
        ->and($recentShift->fresh()->status)->toBe(WorkShiftStatus::Open);
});

it('vernieuwt een clock point QR en laat oude token werken tijdens grace', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'old-clock-token',
        'qr_renewed_at' => now()->subMonths(7),
    ]);
    $oldToken = $clockPoint->qr_token;

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Time\ClockPointsIndex::class)
        ->call('renewQr', $clockPoint->id)
        ->assertHasNoErrors();

    $clockPoint = $clockPoint->fresh();
    expect($clockPoint->qr_token)->not->toBe($oldToken)
        ->and(\App\Models\ClockPointQrToken::query()->where('qr_token', $oldToken)->exists())->toBeTrue();

    Livewire::test(TimePortal::class, ['token' => $oldToken])
        ->assertSet('inactiveReasonKey', null);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->assertSet('inactiveReasonKey', null);
});

it('blokkeert een verlopen QR-token en logt de poging', function () {
    [$tenant] = timeTenantWithAdmin();
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'current-token',
    ]);

    $history = \App\Models\ClockPointQrToken::query()->create([
        'tenant_id' => $tenant->id,
        'clock_point_id' => $clockPoint->id,
        'qr_token' => 'blocked-token',
        'grace_ends_at' => now()->subDay(),
        'blocked_at' => now(),
    ]);

    Livewire::test(TimePortal::class, ['token' => $history->qr_token])
        ->assertSet('inactiveReasonKey', 'portal.inactive.clock_point_qr_expired');

    expect(DB::table('audit_logs')
        ->where('action', 'clock_point.qr_blocked')
        ->where('model_id', $clockPoint->id)
        ->exists())->toBeTrue();
});

it('toont renewal-aanbeveling wanneer aanbevolen datum verstreken is', function () {
    [$tenant] = timeTenantWithAdmin();
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_renewal_recommended_at' => now()->subDay(),
    ]);

    expect($clockPoint->isRenewalRecommended())->toBeTrue();
});

it('laat een admin een gesloten shift corrigeren met auditlog', function () {
    [$tenant, $admin] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    app(ClockOutAction::class)->handle($worker, $clockPoint);

    $newClockIn = $shift->clock_in_at->copy()->subHour();
    $newClockOut = $shift->fresh()->clock_out_at->copy()->subMinutes(30);

    Livewire::actingAs($admin)
        ->test(ShiftsIndex::class)
        ->call('openCorrection', $shift->id)
        ->set('correctionClockIn', $newClockIn->format('Y-m-d\TH:i'))
        ->set('correctionClockOut', $newClockOut->format('Y-m-d\TH:i'))
        ->set('correctionBreakMinutes', 10)
        ->set('correctionReason', 'Vergeten uit te klokken')
        ->call('saveCorrection')
        ->assertHasNoErrors();

    $shift = $shift->fresh();
    expect($shift->clock_in_at->format('Y-m-d H:i'))->toBe($newClockIn->format('Y-m-d H:i'))
        ->and($shift->clock_out_at->format('Y-m-d H:i'))->toBe($newClockOut->format('Y-m-d H:i'))
        ->and($shift->total_break_minutes)->toBe(10);

    expect(DB::table('audit_logs')
        ->where('action', 'work_shift.corrected')
        ->where('model_id', $shift->id)
        ->where('tenant_id', $tenant->id)
        ->exists())->toBeTrue();
});

it('koppelt taken aan een open shift bij start en afhandeling', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'description' => 'Filter vervangen',
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartTaskAction::class)->handle($task, $worker);

    $log = WorkShiftTaskLog::query()->where('task_id', $task->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->ended_at)->toBeNull();

    app(CompleteTaskAction::class)->handle($task->fresh(), $worker);

    expect($log->fresh()->ended_at)->not->toBeNull();
});

it('sluit open taakkoppelingen bij uitklokken', function () {
    [$tenant] = timeTenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartTaskAction::class)->handle($task, $worker);
    app(ClockOutAction::class)->handle($worker, $clockPoint);

    $log = WorkShiftTaskLog::query()->where('task_id', $task->id)->first();
    expect($log?->ended_at)->not->toBeNull();
});
