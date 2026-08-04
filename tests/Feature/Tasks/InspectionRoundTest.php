<?php

declare(strict_types=1);

use App\Actions\Issues\SyncIssueRoundStopsAction;
use App\Actions\Portal\FindNewTeamTasksSinceBaselineAction;
use App\Actions\Portal\SyncWorkerOpenTaskBaselineAction;
use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Actions\Tasks\SkipRoundStopAction;
use App\Actions\Tasks\TaskBelongsToUnitAction;
use App\Enums\TaskStatus;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Portal\UnitPortalData;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     team: InternalTeam,
 *     unitA: Unit,
 *     unitB: Unit,
 *     unitC: Unit,
 *     actor: User,
 *     worker: Worker,
 *     issue: Issue,
 *     task: Task
 * }
 */
function inspectionRoundScaffold(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $actor = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);
    $category->teams()->sync([$team->id]);

    $unitA = Unit::factory()->withQrToken('round-unit-a')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitB = Unit::factory()->withQrToken('round-unit-b')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitC = Unit::factory()->withQrToken('round-unit-c')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'is_recurring' => true,
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    app(SyncIssueRoundStopsAction::class)->handle($issue, [$unitA->id, $unitB->id], $actor);
    $issue = $issue->fresh(['roundStops']);

    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'is_recurring_cycle' => true,
    ]);

    return compact('tenant', 'location', 'team', 'unitA', 'unitB', 'unitC', 'actor', 'worker', 'issue', 'task');
}

it('rejects fewer than two round stops', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $actor = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'is_active' => true]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);

    expect(fn () => app(SyncIssueRoundStopsAction::class)->handle($issue, [$unit->id], $actor))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows a normal non-recurring issue without round-stop validation', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    seedTenantPastOnboarding($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::query()->where('tenant_id', $tenant->id)->first()
        ?? Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::query()->where('tenant_id', $tenant->id)->first()
        ?? Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    // Lege round_stop_unit_ids (default) mag geen inspectieronde-fout geven.
    Livewire::actingAs($user)
        ->test(\App\Livewire\Issues\Index::class)
        ->set('showCreateModal', true)
        ->set('location_id', $location->id)
        ->set('unit_id', $unit->id)
        ->set('description', 'Het gordijn is stuk')
        ->set('is_recurring', false)
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->assertSet('createStep', 2);

    expect(Issue::query()->where('description', 'Het gordijn is stuk')->exists())->toBeTrue()
        ->and(Issue::query()->where('description', 'Het gordijn is stuk')->first()?->isInspectionRound())->toBeFalse();

    // Losse stop-ids (rest van eerdere ronde) mogen gewone meldingen niet blokkeren.
    Livewire::actingAs($user)
        ->test(\App\Livewire\Issues\Index::class)
        ->set('showCreateModal', true)
        ->set('location_id', $location->id)
        ->set('unit_id', $unit->id)
        ->set('description', 'Nog een gewone melding')
        ->set('is_recurring', false)
        ->set('round_stop_unit_ids', [(string) $unit->id])
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->assertSet('createStep', 2)
        ->assertSet('round_stop_unit_ids', []);
});

it('rejects a single round stop on a recurring issue create', function () {
    $ctx = inspectionRoundScaffold();
    seedTenantPastOnboarding($ctx['tenant']);

    Livewire::actingAs($ctx['actor'])
        ->test(\App\Livewire\Issues\Index::class)
        ->set('showCreateModal', true)
        ->set('location_id', $ctx['location']->id)
        ->set('description', 'Ronde met te weinig stops')
        ->set('is_recurring', true)
        ->set('recurrence_interval_value', 1)
        ->set('recurrence_interval_unit', 'month')
        ->set('recurrence_lead_days', 7)
        ->set('recurrence_first_due_date', now()->toDateString())
        ->set('round_stop_unit_ids', [(string) $ctx['unitA']->id])
        ->call('saveCreateStepOne')
        ->assertHasErrors(['round_stop_unit_ids']);
});

it('rejects round stops when unit checks are disabled on a stop', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $actor = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);

    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => false,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);

    expect(fn () => app(SyncIssueRoundStopsAction::class)->handle($issue, [$unitA->id, $unitB->id], $actor))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('removes stops when unit checks are turned off later', function () {
    ['tenant' => $tenant, 'actor' => $actor, 'issue' => $issue, 'unitA' => $unitA, 'unitB' => $unitB, 'unitC' => $unitC] = inspectionRoundScaffold();

    app(SyncIssueRoundStopsAction::class)->handle($issue, [$unitA->id, $unitB->id, $unitC->id], $actor);
    $issue = $issue->fresh(['roundStops']);
    expect($issue->roundStopCount())->toBe(3);

    app(\App\Actions\Locations\UpdateUnitAction::class)->handle($unitC, [
        'name' => $unitC->name,
        'allow_unit_checks' => false,
    ], (int) $actor->id);

    $issue = $issue->fresh(['roundStops']);
    expect($issue->roundStopCount())->toBe(2)
        ->and($issue->roundStops->pluck('unit_id')->map(fn ($id) => (int) $id)->all())
        ->toBe([(int) $unitA->id, (int) $unitB->id]);
});

it('clears a round when disabling checks leaves fewer than two stops', function () {
    ['actor' => $actor, 'issue' => $issue, 'unitA' => $unitA, 'unitB' => $unitB] = inspectionRoundScaffold();

    app(\App\Actions\Locations\UpdateUnitAction::class)->handle($unitB, [
        'name' => $unitB->name,
        'allow_unit_checks' => false,
    ], (int) $actor->id);

    $issue = $issue->fresh(['roundStops']);
    expect($issue->isInspectionRound())->toBeFalse()
        ->and($issue->roundStopCount())->toBe(0);
});

it('self-heals stale round stops when opening the issue show page', function () {
    ['tenant' => $tenant, 'actor' => $actor, 'issue' => $issue, 'unitA' => $unitA, 'unitB' => $unitB, 'unitC' => $unitC] = inspectionRoundScaffold();
    seedTenantPastOnboarding($tenant);

    app(SyncIssueRoundStopsAction::class)->handle($issue, [$unitA->id, $unitB->id, $unitC->id], $actor);
    $unitC->forceFill(['allow_unit_checks' => false])->save();

    Livewire::actingAs($actor)
        ->test(\App\Livewire\Issues\Show::class, ['issue' => $issue->fresh()])
        ->assertOk()
        ->assertSet('round_stop_unit_ids', [(int) $unitA->id, (int) $unitB->id]);

    expect($issue->fresh()->roundStops->pluck('unit_id')->map(fn ($id) => (int) $id)->all())
        ->toBe([(int) $unitA->id, (int) $unitB->id]);
});

it('labels a round issue and clears unit_id', function () {
    ['issue' => $issue, 'unitA' => $unitA, 'unitB' => $unitB] = inspectionRoundScaffold();

    expect($issue->unit_id)->toBeNull()
        ->and($issue->isInspectionRound())->toBeTrue()
        ->and($issue->roundStopCount())->toBe(2)
        ->and(__('issues.card.round_stops', ['count' => 2]))->toContain('2');

    expect(app(TaskBelongsToUnitAction::class)->handle($issue, $unitA))->toBeTrue()
        ->and(app(TaskBelongsToUnitAction::class)->handle($issue, $unitB))->toBeTrue();
});

it('shows round task on stop A open tasks and excludes from A banner (2b)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $actor = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_unit_checks' => true,
    ]);
    $category->teams()->sync([$team->id]);

    $unitA = Unit::factory()->withQrToken('round-banner-a')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitB = Unit::factory()->withQrToken('round-banner-b')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unitC = Unit::factory()->withQrToken('round-banner-c')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);

    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    // Baseline vóór de ronde-taak: die taak is daarna "nieuw".
    app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'is_recurring' => true,
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);
    app(SyncIssueRoundStopsAction::class)->handle($issue, [$unitA->id, $unitB->id], $actor);
    $issue = $issue->fresh(['roundStops']);

    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'is_recurring_cycle' => true,
    ]);

    $extraIssue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unitC->id,
        'approved_at' => now(),
        'status' => TaskStatus::New,
    ]);
    $teamTaskElsewhere = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $extraIssue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    expect(UnitPortalData::allOpenUnitTasks($unitA, (int) $team->id)->pluck('id'))->toContain($task->id)
        ->and(UnitPortalData::allOpenUnitTasks($unitC, (int) $team->id)->pluck('id'))->not->toContain($task->id);

    $onA = app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitA->id);
    $onC = app(FindNewTeamTasksSinceBaselineAction::class)->handle($worker, (int) $unitC->id);

    expect($onA->pluck('id'))->not->toContain($task->id)
        ->and($onA->pluck('id'))->toContain($teamTaskElsewhere->id)
        ->and($onC->pluck('id'))->toContain($task->id)
        ->and($onC->pluck('id'))->not->toContain($teamTaskElsewhere->id);
});

it('does not complete round task until all stops are ok', function () {
    ['unitA' => $unitA, 'unitB' => $unitB, 'team' => $team, 'worker' => $worker, 'task' => $task] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-a'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(app(RoundTaskCompletionAction::class)->isComplete($task->fresh()))->toBeFalse();

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-b'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and(app(RoundTaskCompletionAction::class)->isComplete($task->fresh()))->toBeTrue();
});

it('does not inherit skips from a previous cycle (4b)', function () {
    ['unitA' => $unitA, 'unitB' => $unitB, 'actor' => $actor, 'worker' => $worker, 'issue' => $issue, 'task' => $task, 'team' => $team] = inspectionRoundScaffold();

    app(SkipRoundStopAction::class)->handle($task, (int) $unitA->id, 'Gesloten', $worker);
    // Complete remaining stop via OK would finish cycle 1 — force done for clarity.
    $task->forceFill([
        'status' => TaskStatus::Done,
        'completed_at' => now(),
        'started_at' => now(),
    ])->save();

    $cycle2 = Task::factory()->create([
        'tenant_id' => $issue->tenant_id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'is_recurring_cycle' => true,
        'cycle_number' => 2,
    ]);

    expect(app(RoundTaskCompletionAction::class)->openStopUnitIds($cycle2)->all())
        ->toContain((int) $unitA->id)
        ->toContain((int) $unitB->id)
        ->and(app(RoundTaskCompletionAction::class)->isComplete($cycle2))->toBeFalse();
});

it('prefers single-unit task over round on the same scan', function () {
    ['tenant' => $tenant, 'location' => $location, 'team' => $team, 'unitA' => $unitA, 'worker' => $worker, 'task' => $roundTask] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    $singleIssue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unitA->id,
        'approved_at' => now(),
        'status' => TaskStatus::New,
    ]);
    $singleTask = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $singleIssue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-a'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors();

    expect($singleTask->fresh()->status)->toBe(TaskStatus::Done)
        ->and($roundTask->fresh()->status)->not->toBe(TaskStatus::Done)
        ->and(app(RoundTaskCompletionAction::class)->openStopUnitIds($roundTask->fresh())->all())
        ->not->toContain((int) $unitA->id);
});

it('does not advance round when scanning out of stop order', function () {
    ['unitA' => $unitA, 'unitB' => $unitB, 'team' => $team, 'worker' => $worker, 'task' => $task] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-b'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors()
        ->assertSet('flashMessage', __('portal.round.out_of_order', ['name' => $unitA->localizedName()]));

    expect($task->fresh()->status)->toBe(TaskStatus::New)
        ->and(app(RoundTaskCompletionAction::class)->openStopUnitIds($task->fresh())->all())
        ->toContain((int) $unitA->id)
        ->toContain((int) $unitB->id)
        ->and(app(RoundTaskCompletionAction::class)->isNextOpenStop($task->fresh(), (int) $unitA->id))->toBeTrue();
});

it('refuses skip when the unit is not the next open stop', function () {
    ['unitB' => $unitB, 'worker' => $worker, 'task' => $task] = inspectionRoundScaffold();

    expect(fn () => app(SkipRoundStopAction::class)->handle($task, (int) $unitB->id, 'Later', $worker))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(app(RoundTaskCompletionAction::class)->progress($task->fresh())['stops'][0]['state'])->toBe('current')
        ->and(app(RoundTaskCompletionAction::class)->progress($task->fresh())['stops'][1]['state'])->toBe('open');
});

it('records not_ok on a round stop and advances to the next stop', function () {
    ['unitA' => $unitA, 'unitB' => $unitB, 'team' => $team, 'worker' => $worker, 'task' => $task] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    $checkedAt = now();

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-a'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'not_ok')
        ->set('checkCheckedAt', $checkedAt->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'new')
        ->assertSet('description', __('portal.unit_check.report_prefill_not_ok_round', [
            'datetime' => $checkedAt->timezone(config('app.timezone'))->format('d-m-Y H:i'),
        ]));

    $progress = app(RoundTaskCompletionAction::class)->progress($task->fresh());

    expect(\App\Models\UnitCheck::query()->where('result', 'not_ok')->where('task_id', $task->id)->count())->toBe(1)
        ->and($progress['stops'][0]['state'])->toBe('not_ok')
        ->and($progress['stops'][1]['state'])->toBe('current')
        ->and($progress['next_unit_id'])->toBe((int) $unitB->id)
        ->and($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('does not overwrite an existing report description after round not_ok', function () {
    ['team' => $team, 'worker' => $worker] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-a'])
        ->set('description', 'Deur ging niet open')
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'not_ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors()
        ->assertSet('portalSection', 'new')
        ->assertSet('description', 'Deur ging niet open');
});

it('includes stop timestamp and worker in round progress', function () {
    ['unitA' => $unitA, 'team' => $team, 'worker' => $worker, 'task' => $task] = inspectionRoundScaffold();
    WorkerVerification::markVerified($team, $worker);

    Livewire::test(UnitPortal::class, ['token' => 'round-unit-a'])
        ->call('openSection', 'unit_check')
        ->set('checkResult', 'ok')
        ->set('checkCheckedAt', now()->toIso8601String())
        ->call('submitUnitCheck')
        ->assertHasNoErrors();

    $stop = app(RoundTaskCompletionAction::class)->progress($task->fresh())['stops'][0];

    expect($stop['state'])->toBe('ok')
        ->and($stop['worker_name'])->toBe($worker->displayName())
        ->and($stop['at'])->not->toBeNull()
        ->and($stop['at'])->toContain(now()->format('d/m/Y'));
});

it('renders round progress on the task show page', function () {
    ['tenant' => $tenant, 'actor' => $actor, 'task' => $task, 'unitA' => $unitA] = inspectionRoundScaffold();
    seedTenantPastOnboarding($tenant);

    Livewire::actingAs($actor)
        ->test(\App\Livewire\Tasks\Show::class, ['task' => $task->fresh()])
        ->assertOk()
        ->assertSee(__('tasks.show.round_progress'))
        ->assertSee(__('portal.round.progress', ['done' => 0, 'total' => 2]))
        ->assertSee($unitA->localizedName());
});

it('renders the issue show page for inspection rounds', function () {
    ['tenant' => $tenant, 'actor' => $actor, 'issue' => $issue] = inspectionRoundScaffold();
    seedTenantPastOnboarding($tenant);

    $this->actingAs($actor)
        ->get(route('issues.show', $issue))
        ->assertOk()
        ->assertSee('show_round_stop_unit_ids', false);
});
