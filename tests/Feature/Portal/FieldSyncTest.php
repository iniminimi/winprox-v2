<?php

use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\FieldSyncReceipt;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     team: InternalTeam,
 *     unit: Unit,
 *     worker: Worker,
 *     device: WorkerDevice
 * }
 */
function fieldSyncScaffold(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Category',
        'allow_unit_checks' => true,
    ]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('field-sync-unit')->create([
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

    $device = WorkerDevice::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    return compact('tenant', 'location', 'team', 'unit', 'worker', 'device');
}

function fieldSyncJpeg(): string
{
    return (string) base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
}

function fieldSyncPhoto(string $name = 'field.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, fieldSyncJpeg(), 'image/jpeg');
}

function fieldSyncPost(WorkerDevice $device, array $data, array $photos = [])
{
    if ($photos !== []) {
        $data['photos'] = $photos;
    }

    Tenancy::forget();

    return test()
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->withCookie(WorkerDeviceSession::DEVICE_TOKEN_COOKIE, $device->device_token)
        ->post(route('portal.field-sync'), $data);
}

function fieldSyncApprovedTask(array $ctx, array $taskOverrides = []): Task
{
    $issue = Issue::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'location_id' => $ctx['location']->id,
        'unit_id' => $ctx['unit']->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    return Task::factory()->create(array_merge([
        'tenant_id' => $ctx['tenant']->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $ctx['team']->id,
        'status' => TaskStatus::New,
    ], $taskOverrides));
}

it('records a field-sync unit check with optional note and photos', function () {
    Storage::fake('public');
    $ctx = fieldSyncScaffold();
    $checkId = (string) Str::uuid();

    $response = fieldSyncPost($ctx['device'], [
        'client_id' => $checkId,
        'type' => 'unit.check',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode([
            'result' => 'ok',
            'checked_at' => now()->toIso8601String(),
            'description' => 'Offline nagekeken',
        ]),
    ], [fieldSyncPhoto('check.jpg')]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result.outcome', 'recorded');

    $check = UnitCheck::query()->findOrFail((int) $response->json('result.check_id'));
    expect($check->description)->toBe('Offline nagekeken')
        ->and($check->photos)->toHaveCount(1)
        ->and(Issue::query()->count())->toBe(0);
});

it('synchroniseert FIFO start, melding met foto’s, unit check en afronden zonder duplicaten bij retry', function () {
    Storage::fake('public');
    $ctx = fieldSyncScaffold();
    $task = fieldSyncApprovedTask($ctx);
    $startId = (string) Str::uuid();
    $issueId = (string) Str::uuid();
    $checkId = (string) Str::uuid();
    $completeId = (string) Str::uuid();

    $start = fieldSyncPost($ctx['device'], [
        'client_id' => $startId,
        'type' => 'task.start',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ]);
    $start->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('replayed', false)
        ->assertJsonPath('result.outcome', 'started');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);

    $created = fieldSyncPost($ctx['device'], [
        'client_id' => $issueId,
        'type' => 'issue.create',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode([
            'description' => 'Lekkage in de kelder zonder bereik.',
            'original_language' => 'nl',
        ]),
    ], [
        fieldSyncPhoto('one.jpg'),
        fieldSyncPhoto('two.jpg'),
        fieldSyncPhoto('three.jpg'),
        fieldSyncPhoto('four.jpg'),
    ]);
    $created->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('replayed', false)
        ->assertJsonPath('result.outcome', 'created');

    $newIssue = Issue::query()->findOrFail((int) $created->json('result.issue_id'));
    expect($newIssue->description)->toBe('Lekkage in de kelder zonder bereik.')
        ->and($newIssue->photos()->count())->toBe(4)
        ->and($newIssue->isApproved())->toBeFalse();

    $check = fieldSyncPost($ctx['device'], [
        'client_id' => $checkId,
        'type' => 'unit.check',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode([
            'result' => 'not_ok',
            'checked_at' => now()->toIso8601String(),
        ]),
    ]);
    $check->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result.outcome', 'recorded');

    expect(UnitCheck::query()->count())->toBe(1)
        ->and($task->fresh()->status)->toBe(TaskStatus::InProgress);

    $complete = fieldSyncPost($ctx['device'], [
        'client_id' => $completeId,
        'type' => 'task.complete',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode([
            'task_id' => $task->id,
            'note' => 'Hersteld in de kelder.',
        ]),
    ], [fieldSyncPhoto('done.jpg')]);
    $complete->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result.outcome', 'completed');

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and(IssuePhoto::query()->where('issue_id', $task->issue_id)->count())->toBe(1);

    $replayIssue = fieldSyncPost($ctx['device'], [
        'client_id' => $issueId,
        'type' => 'issue.create',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode([
            'description' => 'Lekkage in de kelder zonder bereik.',
        ]),
    ], [fieldSyncPhoto('retry.jpg')]);
    $replayIssue->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('replayed', true)
        ->assertJsonPath('result.issue_id', $newIssue->id);

    $replayComplete = fieldSyncPost($ctx['device'], [
        'client_id' => $completeId,
        'type' => 'task.complete',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ]);
    $replayComplete->assertOk()
        ->assertJsonPath('replayed', true)
        ->assertJsonPath('result.outcome', 'completed');

    expect(Issue::query()->count())->toBe(2)
        ->and(FieldSyncReceipt::query()->count())->toBe(4)
        ->and($newIssue->photos()->count())->toBe(4);
});

it('accepteert één foto bij een nieuwe melding', function () {
    Storage::fake('public');
    $ctx = fieldSyncScaffold();

    $response = fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'issue.create',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['description' => 'Eén foto mee.']),
    ], [fieldSyncPhoto()]);

    $response->assertOk()->assertJsonPath('ok', true);
    expect(Issue::query()->first()->photos()->count())->toBe(1);
});

it('weigert meer dan vier foto’s zonder receipt', function () {
    $ctx = fieldSyncScaffold();

    $response = fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'issue.create',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['description' => 'Te veel foto’s.']),
    ], [
        fieldSyncPhoto('1.jpg'),
        fieldSyncPhoto('2.jpg'),
        fieldSyncPhoto('3.jpg'),
        fieldSyncPhoto('4.jpg'),
        fieldSyncPhoto('5.jpg'),
    ]);

    $response->assertStatus(422);
    expect(Issue::query()->count())->toBe(0)
        ->and(FieldSyncReceipt::query()->count())->toBe(0);
});

it('weigert een te grote payload zonder receipt', function () {
    $ctx = fieldSyncScaffold();

    $response = fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'issue.create',
        'unit_token' => 'field-sync-unit',
        'payload' => str_repeat('a', 20001),
    ]);

    $response->assertStatus(422);
    expect(FieldSyncReceipt::query()->count())->toBe(0);
});

it('behandelt al gestart of al afgehandeld als succes', function () {
    $ctx = fieldSyncScaffold();
    $task = fieldSyncApprovedTask($ctx, [
        'status' => TaskStatus::InProgress,
        'started_at' => now()->subHour(),
    ]);

    fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.start',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ])->assertOk()->assertJsonPath('result.outcome', 'already_started');

    $task->update([
        'status' => TaskStatus::Done,
        'completed_at' => now(),
    ]);

    fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.complete',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ])->assertOk()->assertJsonPath('result.outcome', 'already_completed');
});

it('weigert een taak of unit van een andere tenant', function () {
    $ctx = fieldSyncScaffold();
    $task = fieldSyncApprovedTask($ctx);

    $other = Tenant::factory()->create();
    Tenancy::actAs($other->id);
    $otherLocation = Location::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);
    $otherCategory = Category::factory()->create([
        'tenant_id' => $other->id,
        'allow_unit_checks' => true,
    ]);
    $otherCategory->teams()->sync([$otherTeam->id]);
    $otherUnit = Unit::factory()->withQrToken('other-tenant-unit')->create([
        'tenant_id' => $other->id,
        'location_id' => $otherLocation->id,
        'category_id' => $otherCategory->id,
        'is_active' => true,
    ]);
    Tenancy::actAs($ctx['tenant']->id);

    fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.start',
        'unit_token' => 'other-tenant-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ])->assertForbidden()->assertJsonPath('ok', false);

    expect(FieldSyncReceipt::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and($otherUnit->id)->toBeGreaterThan(0);

    $foreignTask = Task::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'issue_id' => Issue::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'unit_id' => $ctx['unit']->id,
            'approved_at' => now(),
        ])->id,
        'internal_team_id' => InternalTeam::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'is_active' => true,
        ])->id,
        'status' => TaskStatus::New,
    ]);

    fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.start',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $foreignTask->id]),
    ])->assertStatus(422)->assertJsonPath('ok', false);

    expect($foreignTask->fresh()->status)->toBe(TaskStatus::New);
});

it('weigert field-sync zonder toestelcookie of met onbekende unit-token', function () {
    $ctx = fieldSyncScaffold();
    $task = fieldSyncApprovedTask($ctx);

    test()->withHeaders(['Accept' => 'application/json'])->post(route('portal.field-sync'), [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.start',
        'unit_token' => 'field-sync-unit',
        'payload' => json_encode(['task_id' => $task->id]),
    ])->assertForbidden();

    fieldSyncPost($ctx['device'], [
        'client_id' => (string) Str::uuid(),
        'type' => 'task.start',
        'unit_token' => 'does-not-exist',
        'payload' => json_encode(['task_id' => $task->id]),
    ])->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::New)
        ->and(FieldSyncReceipt::query()->count())->toBe(0);
});
