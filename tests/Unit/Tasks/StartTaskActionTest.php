<?php

use App\Actions\Tasks\StartTaskAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Tenancy::actAs(1);
});

afterEach(function () {
    Tenancy::forget();
});

it('starts a task with current timestamp when no client timestamp provided', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');

    $action = new StartTaskAction(app(App\Support\Audit\AuditRecorder::class));
    $result = $action->handle($task);

    expect($result->status)->toBe(TaskStatus::InProgress);
    expect($result->started_at)->toEqual(Carbon::parse('2024-01-01 12:00:00'));
});

it('starts a task with client timestamp when provided', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 10:30:00');

    $action = new StartTaskAction(app(App\Support\Audit\AuditRecorder::class));
    $result = $action->handle($task, null, $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::InProgress);
    expect($result->started_at)->toEqual($clientTimestamp);
    expect($result->started_at->toDateTimeString())->toBe('2024-01-01 10:30:00');
});

it('does not override existing started_at when task already started', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $existingStartedAt = Carbon::parse('2024-01-01 09:00:00');
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => $existingStartedAt,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 10:30:00');

    $action = new StartTaskAction(app(App\Support\Audit\AuditRecorder::class));
    $result = $action->handle($task, null, $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::InProgress);
    expect($result->started_at)->toEqual($existingStartedAt);
});

it('accepts worker parameter for audit purposes', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => null,
    ]);

    Event::fake();

    $action = new StartTaskAction(app(App\Support\Audit\AuditRecorder::class));
    $result = $action->handle($task, $worker);

    Event::assertDispatched(\App\Events\Tasks\TaskStarted::class);
});

it('returns task unchanged when task cannot be started', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::Done,
        'started_at' => null,
    ]);

    $originalStatus = $task->status;
    $originalStartedAt = $task->started_at;

    $action = new StartTaskAction(app(App\Support\Audit\AuditRecorder::class));
    $result = $action->handle($task);

    expect($result->status)->toBe($originalStatus);
    expect($result->started_at)->toBe($originalStartedAt);
});
