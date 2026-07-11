<?php

declare(strict_types=1);

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\TaskStatus;
use App\Livewire\Esg\Dashboard;
use App\Models\AuditLog;
use App\Models\EsgIndicator;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     unit: Unit,
 *     indicator: EsgIndicator,
 *     issue: Issue,
 *     task: Task,
 *     team: InternalTeam,
 * }
 */
function esgThresholdFixture(float $thresholdMax = 100.0): array
{
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Blok A']);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Gasmeter']);
    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'thresholds' => ['min' => 0, 'max' => $thresholdMax],
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
    ]);

    return compact('tenant', 'location', 'unit', 'indicator', 'issue', 'task', 'team');
}

it('maakt een opvolgtaak bij een meting buiten drempel', function () {
    $fixture = esgThresholdFixture();

    $measurement = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $followUp = Task::query()
        ->where('esg_threshold_measurement_id', $measurement->id)
        ->first();

    expect($followUp)->not->toBeNull()
        ->and($followUp->internal_team_id)->toBe($fixture['team']->id)
        ->and($followUp->issue?->approved_at)->not->toBeNull()
        ->and($followUp->issue?->esg_indicator_id)->toBe($fixture['indicator']->id)
        ->and($followUp->priority->value)->toBe('prio_2');

    expect(AuditLog::query()->where('action', 'esg_threshold_follow_up.created')->exists())->toBeTrue();
});

it('maakt geen opvolgtaak bij een meting binnen drempel', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 50.0,
        ),
        $fixture['tenant']->id,
    );

    expect(Task::query()->whereNotNull('esg_threshold_measurement_id')->count())->toBe(0);
});

it('onderdrukt dubbele opvolgtaken voor dezelfde indicator en unit', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->addHour()->toImmutable(),
            valueNumeric: 180.0,
        ),
        $fixture['tenant']->id,
    );

    expect(Task::query()->whereNotNull('esg_threshold_measurement_id')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'esg_threshold_follow_up.skipped_duplicate')->exists())->toBeTrue();
});

it('maakt opnieuw een opvolgtaak nadat de vorige is afgerond', function () {
    $fixture = esgThresholdFixture();

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    $firstFollowUp = Task::query()->whereNotNull('esg_threshold_measurement_id')->first();
    app(UpdateTaskStatusAction::class)->handle($firstFollowUp, TaskStatus::Done);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->addHours(2)->toImmutable(),
            valueNumeric: 200.0,
        ),
        $fixture['tenant']->id,
    );

    expect(Task::query()->whereNotNull('esg_threshold_measurement_id')->count())->toBe(2);
});

it('stuurt esg.threshold.follow_up_created webhook', function () {
    Http::fake();

    $fixture = esgThresholdFixture();
    WebhookEndpoint::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'url' => 'https://example.test/esg-threshold',
        'events' => ['esg.threshold.follow_up_created'],
    ]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.test/esg-threshold'
            && ($request->header('X-WinProx-Event')[0] ?? '') === 'esg.threshold.follow_up_created';
    });
});

it('toont auto-alarm en opvolgtaak op het dashboard', function () {
    $fixture = esgThresholdFixture();
    $user = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 150.0,
        ),
        $fixture['tenant']->id,
    );

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('esg.dashboard.auto_task.pill'))
        ->assertSee(__('esg.dashboard.auto_task.follow_up'));
});

it('bevat esg.threshold.follow_up_created in AVAILABLE_EVENTS', function () {
    expect(\App\Models\WebhookEndpoint::AVAILABLE_EVENTS)->toContain('esg.threshold.follow_up_created');
});
