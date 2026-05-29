<?php

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

    $issue = app(CreateIssueAction::class)->handle(['description' => 'Lekkende kraan']);
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

    app(UpdateTaskStatusAction::class)->handle($taskA, TaskStatus::Closed);
    app(UpdateTaskStatusAction::class)->handle($taskB, TaskStatus::Closed);
    expect($issue->fresh()->status)->toBe(TaskStatus::Closed);
});

it('maakt één taak per team aan via CreateIssueAction', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $issue = app(CreateIssueAction::class)->handle(['description' => 'Onderhoud'], [$teamA->id, $teamB->id]);

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
