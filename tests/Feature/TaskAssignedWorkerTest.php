<?php

use App\Actions\Issues\AssignIssueTeamTaskAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskAssignmentAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Portal\TimePortalData;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Tenancy::forget());

it('wijst optioneel een worker toe aan een teamtaak', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    $task = app(AssignIssueTeamTaskAction::class)->handle(
        $issue,
        $team->id,
        'Poetsen',
        assignedWorkerId: $worker->id,
    );

    expect($task->assigned_worker_id)->toBe($worker->id)
        ->and($task->internal_team_id)->toBe($team->id);
});

it('weigert een worker van een ander team', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $otherWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $otherTeam->id,
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
    ]);

    expect(fn () => app(CreateTaskAction::class)->handle(
        $issue,
        $team->id,
        assignedWorkerId: $otherWorker->id,
    ))->toThrow(ValidationException::class);
});

it('toont toegewezen taken alleen aan die worker op Clock Point', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $assignee = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $colleague = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
    ]);

    $assigned = app(CreateTaskAction::class)->handle(
        $issue,
        $team->id,
        status: TaskStatus::New,
        assignedWorkerId: $assignee->id,
    );
    $openTeam = app(CreateTaskAction::class)->handle(
        $issue,
        $team->id,
        status: TaskStatus::New,
    );

    $forAssignee = TimePortalData::openTasksForWorker($assignee)->pluck('id');
    $forColleague = TimePortalData::openTasksForWorker($colleague)->pluck('id');

    expect($forAssignee)->toContain($assigned->id)->toContain($openTeam->id)
        ->and($forColleague)->toContain($openTeam->id)
        ->and($forColleague)->not->toContain($assigned->id);
});

it('laat beheer de worker wissen of wijzigen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $workerA = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $workerB = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
    ]);

    $task = app(CreateTaskAction::class)->handle(
        $issue,
        $team->id,
        assignedWorkerId: $workerA->id,
    );

    $task = app(UpdateTaskAssignmentAction::class)->handle($task, $team->id, $workerB->id);
    expect($task->assigned_worker_id)->toBe($workerB->id);

    $task = app(UpdateTaskAssignmentAction::class)->handle($task, $team->id, null);
    expect($task->assigned_worker_id)->toBeNull();
});
