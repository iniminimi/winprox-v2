<?php

use App\Actions\Time\AssertClockPointTaskVisitAction;
use App\Actions\Time\AssertClockPointUnitVisitAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Enums\TaskStatus;
use App\Enums\UnitCheckResult;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\IssueRoundStop;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Tenancy::forget());

function clockPointTaskIssue(array $ctx, array $issueAttrs = []): Issue
{
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;

    return Issue::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
        'description' => 'Kamer poetsen zonder sticker',
    ], $issueAttrs));
}

function clockPointOpenTask(array $ctx, Issue $issue, array $taskAttrs = []): Task
{
    [$tenant, $worker] = $ctx;

    return Task::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::New,
        'description' => $issue->description,
    ], $taskAttrs));
}

function prepareClockPointWorker(array $ctx): array
{
    ensureTestEncryptionKey();
    [$tenant, $worker, $clockPoint] = $ctx;
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'cp-tasks-'.$tenant->id.'-'.uniqid()]);

    return $ctx;
}

it('start en handelt een taak af op Clock Point met open GPS-bezoek', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $issue = clockPointTaskIssue($ctx);
    $task = clockPointOpenTask($ctx, $issue);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    $portal = signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee(__('portal.team.complete_on_site_hint'), false)
        ->assertSee(__('portal.worker.start_task'), false)
        ->call('startTask', $task->id)
        ->assertSet('flashMessage', __('portal.worker.task_started'));

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);

    $portal->call('beginCompleteTask', $task->id)
        ->set('completingNote', 'Klaar, geen sticker nodig.')
        ->call('submitCompleteTask')
        ->assertHasNoErrors()
        ->assertSet('flashMessage', __('portal.worker.task_completed'));

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($issue->updates()->where('kind', 'worker_note')->count())->toBe(1);
});

it('bewaart afhandelingsfoto’s via Clock Point gekoppeld aan de taak', function () {
    Storage::fake('public');
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $issue = clockPointTaskIssue($ctx);
    $task = clockPointOpenTask($ctx, $issue, [
        'status' => TaskStatus::InProgress,
        'started_at' => now()->subHour(),
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
    $file = UploadedFile::fake()->createWithContent('clock-done.jpg', $jpeg, 'image/jpeg');

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->call('beginCompleteTask', $task->id)
        ->set('completingPhotos', [$file])
        ->call('submitCompleteTask')
        ->assertHasNoErrors();

    $update = $issue->fresh()->updates()->where('kind', 'worker_photos')->first();
    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($update)->not->toBeNull()
        ->and($update?->task_id)->toBe($task->id);
});

it('weigert taakacties zonder open werkbezoek', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint] = $ctx;
    $issue = clockPointTaskIssue($ctx);
    $task = clockPointOpenTask($ctx, $issue);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee(__('portal.team.complete_needs_visit'), false)
        ->assertDontSee(__('portal.worker.start_task'), false)
        ->call('startTask', $task->id)
        ->assertSet('flashMessage', __('portal.team.complete_needs_visit'));

    expect($task->fresh()->status)->toBe(TaskStatus::New);
});

it('weigert taakacties op een andere locatie dan het open bezoek', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $otherLocation = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Andere klant',
    ]);
    $issue = clockPointTaskIssue($ctx, [
        'location_id' => $otherLocation->id,
        'unit_id' => null,
        'description' => 'Taak bij andere klant',
    ]);
    $task = clockPointOpenTask($ctx, $issue);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee(__('portal.worker.errors.not_this_location'), false)
        ->assertDontSee(__('portal.worker.start_task'), false)
        ->call('startTask', $task->id)
        ->assertSet('flashMessage', __('portal.worker.errors.not_this_location'));

    expect($task->fresh()->status)->toBe(TaskStatus::New);
});

it('houdt Clock Point alleen-lezen zonder GPS-werkbezoeken', function () {
    ensureTestEncryptionKey();
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_gps_visits' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'cp-readonly-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);
    $ctx = [$tenant, $worker, $clockPoint, $location, $unit];
    $issue = clockPointTaskIssue($ctx);
    $task = clockPointOpenTask($ctx, $issue);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee(__('portal.team.read_only_hint'), false)
        ->assertDontSee(__('portal.worker.start_task'), false)
        ->call('startTask', $task->id)
        ->assertSet('flashMessage', __('portal.team.read_only_hint'));

    expect($task->fresh()->status)->toBe(TaskStatus::New);
});

it('laat inspectierondes niet starten via Clock Point', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Stop B',
        'is_active' => true,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'description' => 'Ronde niet via Clock Point',
        'recurrence_next_due_at' => now()->endOfDay(),
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    $task = clockPointOpenTask($ctx, $issue, ['status' => TaskStatus::InProgress]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    expect(fn () => app(AssertClockPointTaskVisitAction::class)->handle($worker, $task->load('issue')))
        ->toThrow(InvalidArgumentException::class, 'clock_point_task_read_only');

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee('Ronde niet via Clock Point', false)
        ->assertSee(__('time.portal.today.do_check'), false)
        ->call('startTask', $task->id)
        ->call('beginCompleteTask', $task->id)
        ->assertSet('completingTaskId', null);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('handelt de volgende inspectiestop af op Clock Point zonder unit-QR', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $unit->update(['allow_unit_checks' => true]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Stop zonder pin',
        'latitude' => null,
        'longitude' => null,
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'description' => 'Ronde alle units',
        'recurrence_next_due_at' => now()->endOfDay(),
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    $task = clockPointOpenTask($ctx, $issue, ['status' => TaskStatus::InProgress]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee('Ronde alle units', false)
        ->assertSee(__('portal.round.next_stop', ['name' => $unit->name]), false)
        ->assertDontSeeHtml('wp-round-progress')
        ->call('openClockPointUnitCheck', $unit->id)
        ->assertSet('checkingUnitId', $unit->id)
        ->assertSeeHtml('wp-round-progress')
        ->call('submitClockPointUnitCheck', 'ok')
        ->assertSet('checkingUnitId', null)
        ->assertSet('flashMessage', __('portal.unit_check.recorded_ok'));

    expect(UnitCheck::query()->where('unit_id', $unit->id)->where('task_id', $task->id)->value('result'))
        ->toBe(UnitCheckResult::Ok)
        ->and($task->fresh()->status)->toBe(TaskStatus::InProgress);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->call('openClockPointUnitCheck', $unitB->id)
        ->call('submitClockPointUnitCheck', 'ok')
        ->assertSet('flashMessage', __('portal.unit_check.recorded_ok'));

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('weigert een unit-check op Clock Point op een andere locatie', function () {
    $ctx = prepareClockPointWorker(gpsVisitContext());
    [$tenant, $worker, $clockPoint, $location, $unit] = $ctx;
    $otherLocation = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Andere klant',
        'street' => 'Kerkstraat',
        'house_number' => '1',
        'postal_code' => '8000',
        'city' => 'Brugge',
    ]);
    $otherUnit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $otherLocation->id,
        'name' => 'Andere stop',
        'is_active' => true,
        'allow_unit_checks' => true,
    ]);
    $unit->update(['allow_unit_checks' => true]);

    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(StartWorkVisitAction::class)->handle($worker, $unit, 51.05, 3.73);

    expect(fn () => app(AssertClockPointUnitVisitAction::class)->handle($worker, $otherUnit))
        ->toThrow(InvalidArgumentException::class, 'clock_point_visit_location_mismatch');

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->call('openClockPointUnitCheck', $otherUnit->id)
        ->assertSet('checkingUnitId', null)
        ->assertSet('flashMessage', __('portal.worker.errors.not_this_location'));
});
