<?php

use App\Actions\Tasks\CompleteTaskAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\TaskStatus;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
});

afterEach(function () {
    Tenancy::forget();
});

it('completes a task with current timestamp when no client timestamp provided', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => Carbon::parse('2024-01-01 10:00:00'),
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');

    $action = app(CompleteTaskAction::class);
    $result = $action->handle($task, $worker, 'Intercom was ok');

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->completed_at)->toEqual(Carbon::parse('2024-01-01 12:00:00'));

    $update = $issue->updates()->where('kind', 'worker_note')->first();
    expect($update)->not->toBeNull()
        ->and($update?->task_id)->toBe($task->id)
        ->and($update?->description)->toBe('Intercom was ok');
});

it('stores note and photos on one task-scoped update when completing', function () {
    Storage::fake('public');

    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => Carbon::parse('2024-01-01 10:00:00'),
        'completed_at' => null,
    ]);

    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
    $file = UploadedFile::fake()->createWithContent('done.jpg', $jpeg, 'image/jpeg');

    app(CompleteTaskAction::class)->handle($task, $worker, 'Klaar met foto', [$file]);

    $updates = $issue->updates()->get();
    expect($updates)->toHaveCount(1);

    $update = $updates->first();
    expect($update->task_id)->toBe($task->id)
        ->and($update->kind)->toBe('worker_note')
        ->and($update->description)->toBe('Klaar met foto')
        ->and($update->photos)->toHaveCount(1)
        ->and($update->photos->first()?->tenant_id)->toBe($tenantId)
        ->and($update->photos->first()?->hasPublicFile())->toBeTrue();
});

it('completes a task with client timestamp when provided', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => Carbon::parse('2024-01-01 10:00:00'),
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:30:00');

    $action = app(CompleteTaskAction::class);
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->completed_at)->toEqual($clientTimestamp);
    expect($result->completed_at->toDateTimeString())->toBe('2024-01-01 11:30:00');
});

it('uses client timestamp for started_at when task has no started_at', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => null,
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:00:00');

    $action = app(CompleteTaskAction::class);
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->started_at)->toEqual($clientTimestamp);
    expect($result->completed_at)->toEqual($clientTimestamp);
});

it('preserves existing started_at when task already started', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $existingStartedAt = Carbon::parse('2024-01-01 09:00:00');
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => $existingStartedAt,
        'completed_at' => null,
    ]);

    Carbon::setTestNow('2024-01-01 12:00:00');
    $clientTimestamp = Carbon::parse('2024-01-01 11:30:00');

    $action = app(CompleteTaskAction::class);
    $result = $action->handle($task, $worker, null, [], $clientTimestamp);

    expect($result->status)->toBe(TaskStatus::Done);
    expect($result->started_at)->toEqual($existingStartedAt);
    expect($result->completed_at)->toEqual($clientTimestamp);
});

it('returns task unchanged when task cannot be completed', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenantId, 'approved_at' => now()]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
        'started_at' => null,
        'completed_at' => null,
    ]);

    $originalStatus = $task->status;
    $originalCompletedAt = $task->completed_at;

    $action = app(CompleteTaskAction::class);
    $result = $action->handle($task, $worker);

    expect($result->status)->toBe($originalStatus);
    expect($result->completed_at)->toBe($originalCompletedAt);
});

it('records an esg measurement when completing an esg-linked task', function () {
    $tenantId = Tenancy::id();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    $worker = Worker::factory()->create(['tenant_id' => $tenantId, 'internal_team_id' => $team->id]);
    $indicator = EsgIndicator::factory()->numeric()->create(['tenant_id' => $tenantId]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenantId,
        'approved_at' => now(),
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
        'unit_id' => Unit::factory()->create(['tenant_id' => $tenantId])->id,
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantId,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::InProgress,
        'started_at' => now(),
    ]);

    Tenant::query()->whereKey($tenantId)->update(['has_esg_module' => true]);

    $data = new RecordEsgMeasurementData(
        taskId: $task->id,
        esgIndicatorId: $indicator->id,
        recordedAt: now()->toImmutable(),
        valueNumeric: 99.5,
    );

    $result = app(CompleteTaskAction::class)->handle($task, $worker, null, [], null, $data);

    expect($result->status)->toBe(TaskStatus::Done)
        ->and(EsgMeasurement::count())->toBe(1)
        ->and((float) EsgMeasurement::query()->value('value_numeric'))->toBe(100.0);
});
