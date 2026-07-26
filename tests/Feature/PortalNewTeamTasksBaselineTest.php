<?php

use App\Actions\Portal\FindNewTeamTasksSinceBaselineAction;
use App\Actions\Portal\MarkTeamTasksSeenInBaselineAction;
use App\Actions\Portal\SyncWorkerOpenTaskBaselineAction;
use App\Enums\TaskStatus;
use App\Livewire\Public\TimePortal;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     team: InternalTeam,
 *     worker: Worker,
 *     unitA: Unit,
 *     unitB: Unit,
 *     location: Location,
 *     clockPoint: ClockPoint
 * }
 */
function newTeamTasksScaffold(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $unitA = Unit::factory()->withQrToken('unit-a-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);
    $unitB = Unit::factory()->withQrToken('unit-b-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'clock-baseline-token',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    return compact('tenant', 'team', 'worker', 'unitA', 'unitB', 'location', 'clockPoint');
}

function approvedTeamTask(Tenant $tenant, Location $location, Unit $unit, InternalTeam $team): Task
{
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    return Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);
}

it('zet baseline bij clock point-weergave en toont geen banner voor bestaande taken', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitA' => $unitA, 'unitB' => $unitB, 'location' => $location] = newTeamTasksScaffold();

    approvedTeamTask($tenant, $location, $unitA, $team);
    approvedTeamTask($tenant, $location, $unitB, $team);

    WorkerVerification::markVerified($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    Livewire::test(UnitPortal::class, ['token' => 'unit-a-token'])
        ->assertDontSee(__('portal.worker.open_login_overview'), false);
});

it('toont banner op unit-portaal voor nieuwe teamtaak elders sinds baseline', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitA' => $unitA, 'unitB' => $unitB, 'location' => $location, 'clockPoint' => $clockPoint] = newTeamTasksScaffold();

    approvedTeamTask($tenant, $location, $unitA, $team);

    WorkerVerification::markVerified($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    approvedTeamTask($tenant, $location, $unitB, $team);

    Livewire::test(UnitPortal::class, ['token' => 'unit-a-token'])
        ->assertSee(trans_choice('portal.worker.new_team_tasks_banner', 1, ['count' => 1]), false)
        ->assertSee(__('portal.worker.open_login_overview'), false)
        ->assertSee($clockPoint->portalUrl(), false);
});

it('toont geen banner voor nieuwe taak op de huidige unit', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitA' => $unitA, 'location' => $location] = newTeamTasksScaffold();

    WorkerVerification::markVerified($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    approvedTeamTask($tenant, $location, $unitA, $team);

    Livewire::test(UnitPortal::class, ['token' => 'unit-a-token'])
        ->assertDontSee(__('portal.worker.open_login_overview'), false);
});

it('markeert nieuwe taken als gezien na dismiss', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitA' => $unitA, 'unitB' => $unitB, 'location' => $location] = newTeamTasksScaffold();

    WorkerVerification::markVerified($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    approvedTeamTask($tenant, $location, $unitB, $team);

    expect(app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitA->id))->toHaveCount(1);

    Livewire::test(UnitPortal::class, ['token' => 'unit-a-token'])
        ->call('dismissNewTeamTasksBanner')
        ->assertDontSee(__('portal.worker.open_login_overview'), false);

    expect(app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitA->id))->toHaveCount(0);
});

it('vernieuwt baseline via time-portaal render zodat zichtbare taken geen banner meer geven', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitA' => $unitA, 'unitB' => $unitB, 'location' => $location] = newTeamTasksScaffold();

    WorkerVerification::markVerified($team, $worker);
    WorkerDeviceSession::bindRememberedWorker($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    approvedTeamTask($tenant, $location, $unitB, $team);

    expect(app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitA->id))->toHaveCount(1);

    Livewire::test(TimePortal::class, ['token' => 'clock-baseline-token'])
        ->assertSee(__('time.portal.title'), false);

    expect(app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitA->id))->toHaveCount(0);
});

it('mark team tasks seen action voegt ids toe aan baseline', function () {
    ['tenant' => $tenant, 'team' => $team, 'worker' => $worker, 'unitB' => $unitB, 'location' => $location] = newTeamTasksScaffold();

    WorkerVerification::markVerified($team, $worker);
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    $task = approvedTeamTask($tenant, $location, $unitB, $team);

    app(MarkTeamTasksSeenInBaselineAction::class)->handle($worker, [(int) $task->id]);

    expect(app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker))->toHaveCount(0);
});
