<?php

use App\Actions\Tasks\CompleteTaskAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Tenancy::setTenant(1);
});

afterEach(function () {
    Tenancy::forgetTenant();
});

it('completes a task with current timestamp when no client timestamp provided', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => Carbon::parse('2024-01-01 10:00:00'),
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');

    $action = new CompleteTaskAction(
        app(App\Support\IssuePhotoStorage::class),
        app(App\Support\Audit\AuditRecorder::class),
    );
    $result = $action->handle($task, $worker);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->completed_at)->toEqual(Carbon::parse('2024-01-01 12:00:00'));
});

it('completes a task with client timestamp when provided', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => Carbon::parse('2024-01-01 10:00:00'),
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:30:00');

    $action = new CompleteTaskAction(
        app(App\Support\IssuePhotoStorage::class),
        app(App\Support\Audit\AuditRecorder::class),
    );
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->completed_at)->toEqual($clientTimestamp);
    expect($result->completed_at->toDateTimeString())->toBe('2024-01-01 11:30:00');
});

it('uses client timestamp for started_at when task has no started_at', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => null,
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:00:00');

    $action = new CompleteTaskAction(
        app(App\Support\IssuePhotoStorage::class),
        app(App\Support\Audit\AuditRecorder::class),
    );
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->started_at)->toEqual($clientTimestamp);
    expect($result->completed_at)->toEqual($clientTimestamp);
});

it('preserves existing started_at when task already started', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $existingStartedAt = Carbon::parse('2024-01-01 09:00:00');
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => $existingStartedAt,
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:30:00');

    $action = new CompleteTaskAction(
        app(App\Support\IssuePhotoStorage::class),
        app(App\Support\Audit\AuditRecorder::class),
    );
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->started_at)->toEqual($existingStartedAt);
    expect($result->completed_at)->toEqual($clientTimestamp);
});

it('returns task unchanged when task cannot be completed', function () {
    $team = InternalTeam::factory()->create(['tenant_id' => 1]);
    $worker = Worker::factory()->create(['tenant_id' => 1, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => 1, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => 1,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => null,
        'completed_at' => null,
    ]);

    $originalStatus = $task->status;
    $originalCompletedAt = $task->completed_at;

    $action = new CompleteTaskAction(
        app(App\Support\IssuePhotoStorage::class),
        app(App\Support\Audit\AuditRecorder::class),
    );
    $result = $action->handle($task, $worker);

    expect($result->status)->toBe($originalStatus);
    expect($result->completed_at)->toBe($originalCompletedAt);
});
