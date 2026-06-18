<?php

use App\Actions\Issues\AddIssueUpdateAction;
use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CloseIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('leidt de meldingstatus af uit de taken (rollup)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $issue = app(CreateIssueAction::class)->handle(
        ['description' => 'Lekkende kraan', 'source' => 'qr'],
        [$teamA->id, $teamB->id],
    );
    app(ApproveIssueAction::class)->handle($issue, $user);

    expect($issue->fresh()->status)->toBe(TaskStatus::New);

    $taskA = $issue->tasks->firstWhere('internal_team_id', $teamA->id);
    $taskB = $issue->tasks->firstWhere('internal_team_id', $teamB->id);

    app(UpdateTaskStatusAction::class)->handle($taskA, TaskStatus::InProgress);
    expect($issue->fresh()->status)->toBe(TaskStatus::InProgress);

    app(UpdateTaskStatusAction::class)->handle($taskA, TaskStatus::Done);
    app(UpdateTaskStatusAction::class)->handle($taskB, TaskStatus::Done);
    expect($issue->fresh()->status)->toBe(TaskStatus::Done);

    $closedIssue = app(CreateIssueAction::class)->handle(['description' => 'Gesloten flow', 'source' => 'qr'], [$teamA->id]);
    app(ApproveIssueAction::class)->handle($closedIssue, $user);
    $closedTask = $closedIssue->tasks->first();
    app(UpdateTaskStatusAction::class)->handle($closedTask, TaskStatus::InProgress);
    app(UpdateTaskStatusAction::class)->handle($closedTask, TaskStatus::Closed, null, 'Niet uitgevoerd');
    expect($closedIssue->fresh()->status)->toBe(TaskStatus::Closed);
});

it('maakt één taak per team aan via CreateIssueAction', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $issue = app(CreateIssueAction::class)->handle(['description' => 'Onderhoud', 'source' => 'qr'], [$teamA->id, $teamB->id]);

    expect($issue->tasks()->count())->toBe(2)
        ->and($issue->status)->toBe(TaskStatus::New);
});

it('scheidt data strikt per tenant (global scope)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    Issue::factory()->create(['tenant_id' => $a->id]);
    Issue::factory()->create(['tenant_id' => $b->id]);
    Issue::factory()->create(['tenant_id' => $b->id]);

    Tenancy::actAs($a->id);
    expect(Issue::count())->toBe(1);

    Tenancy::actAs($b->id);
    expect(Issue::count())->toBe(2);

    // Geen context (bv. platform-superuser): ziet alles.
    Tenancy::forget();
    expect(Issue::count())->toBe(3);
});

it('weigert taak aanmaken voor gesloten melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = app(CreateIssueAction::class)->handle(['description' => 'Gesloten melding', 'source' => 'qr'], [$team->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(ApproveIssueAction::class)->handle($issue, $user);
    $task = $issue->tasks->first();

    app(UpdateTaskStatusAction::class)->handle($task, TaskStatus::Closed, null, 'Niet uitgevoerd');
    $issue->refresh();
    expect($issue->status)->toBe(TaskStatus::Closed);
    expect($issue->isClosed())->toBeTrue();

    expect(fn () => app(CreateTaskAction::class)->handle($issue, $team->id))
        ->toThrow(\InvalidArgumentException::class, 'Cannot create task for closed issue');
});

it('weigert update toevoegen aan gesloten melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = app(CreateIssueAction::class)->handle(['description' => 'Gesloten melding', 'source' => 'qr'], [$team->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(ApproveIssueAction::class)->handle($issue, $user);
    $task = $issue->tasks->first();

    app(UpdateTaskStatusAction::class)->handle($task, TaskStatus::Closed, null, 'Niet uitgevoerd');
    $issue->refresh();
    expect($issue->status)->toBe(TaskStatus::Closed);
    expect($issue->isClosed())->toBeTrue();

    expect(fn () => app(AddIssueUpdateAction::class)->handle($issue, 'Dit mag niet', null, null, 'note'))
        ->toThrow(\InvalidArgumentException::class, 'Cannot add update to closed issue');
});

it('sluit een ongekeurde melding met sluitreden in de tijdlijn', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = app(CreateIssueAction::class)->handle(['description' => 'Malafide melding', 'source' => 'qr'], [$team->id]);

    expect($issue->isApproved())->toBeFalse();

    app(CloseIssueAction::class)->handle($issue, $user, 'Geen geldige melding');

    $issue->refresh();

    expect($issue->status)->toBe(TaskStatus::Closed)
        ->and($issue->updates()->where('kind', 'close_reason')->count())->toBe(1)
        ->and($issue->updates()->first()->body)->toBe('Geen geldige melding');
});
