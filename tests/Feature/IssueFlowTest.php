<?php

use App\Actions\Issues\AddIssueUpdateAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Tenant;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('leidt de meldingstatus af uit de taken (rollup)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = app(CreateIssueAction::class)->handle(['description' => 'Lekkende kraan', 'source' => 'qr']);
    expect($issue->status)->toBe(TaskStatus::New);

    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $taskA = app(CreateTaskAction::class)->handle($issue, $teamA->id);
    $taskB = app(CreateTaskAction::class)->handle($issue, $teamB->id);
    expect($issue->fresh()->status)->toBe(TaskStatus::New);

    app(UpdateTaskStatusAction::class)->handle($taskA, TaskStatus::InProgress);
    expect($issue->fresh()->status)->toBe(TaskStatus::InProgress);

    app(UpdateTaskStatusAction::class)->handle($taskA, TaskStatus::Done);
    app(UpdateTaskStatusAction::class)->handle($taskB, TaskStatus::Done);
    expect($issue->fresh()->status)->toBe(TaskStatus::Done);

    $closedIssue = app(CreateIssueAction::class)->handle(['description' => 'Gesloten flow', 'source' => 'qr'], [$teamA->id]);
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
    $task = $issue->tasks->first();

    app(UpdateTaskStatusAction::class)->handle($task, TaskStatus::Closed, null, 'Niet uitgevoerd');
    $issue->refresh();
    expect($issue->status)->toBe(TaskStatus::Closed);
    expect($issue->isClosed())->toBeTrue();

    expect(fn () => app(AddIssueUpdateAction::class)->handle($issue, 'Dit mag niet', null, null, 'note'))
        ->toThrow(\InvalidArgumentException::class, 'Cannot add update to closed issue');
});
