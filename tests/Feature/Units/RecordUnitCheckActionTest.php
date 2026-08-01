<?php

declare(strict_types=1);

use App\Actions\Units\RecordUnitCheckAction;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Events\Units\UnitCheckRecorded;
use App\Models\AuditLog;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::forget());

it('records an ok unit check for a worker', function () {
    Event::fake([UnitCheckRecorded::class]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    $checkedAt = CarbonImmutable::parse('2026-08-01T10:15:00+02:00');

    $check = app(RecordUnitCheckAction::class)->handle(
        unit: $unit,
        data: new RecordUnitCheckData(
            result: UnitCheckResult::Ok,
            checkedAt: $checkedAt,
            source: UnitCheckSource::Portal,
            latitude: 51.05,
            longitude: 3.72,
        ),
        tenantId: $tenant->id,
        worker: $worker,
    );

    expect($check)->toBeInstanceOf(UnitCheck::class)
        ->and($check->result)->toBe(UnitCheckResult::Ok)
        ->and($check->source)->toBe(UnitCheckSource::Portal)
        ->and($check->unit_id)->toBe($unit->id)
        ->and($check->location_id)->toBe($location->id)
        ->and($check->worker_id)->toBe($worker->id)
        ->and($check->internal_team_id)->toBe($team->id)
        ->and($check->latitude)->toBe(51.05)
        ->and($check->longitude)->toBe(3.72)
        ->and($check->task_id)->toBeNull()
        ->and($check->checklist_items)->toBeNull()
        ->and($check->checked_at->toIso8601String())->toBe($checkedAt->toIso8601String());

    Event::assertDispatched(UnitCheckRecorded::class);
});

it('records not_ok without creating an issue', function () {
    Event::fake([UnitCheckRecorded::class]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    $check = app(RecordUnitCheckAction::class)->handle(
        unit: $unit,
        data: new RecordUnitCheckData(
            result: UnitCheckResult::NotOk,
            checkedAt: CarbonImmutable::now(),
        ),
        tenantId: $tenant->id,
    );

    expect($check->result)->toBe(UnitCheckResult::NotOk)
        ->and($check->issue_id)->toBeNull()
        ->and(\App\Models\Issue::query()->count())->toBe(0);
});

it('writes audit log via unit.check.recorded webhook event', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    app(RecordUnitCheckAction::class)->handle(
        unit: $unit,
        data: new RecordUnitCheckData(
            result: UnitCheckResult::Ok,
            checkedAt: CarbonImmutable::now(),
            source: UnitCheckSource::Api,
        ),
        tenantId: $tenant->id,
        actorUserId: $user->id,
    );

    $log = AuditLog::query()->where('action', 'unit.check.recorded')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->model_type)->toBe(Unit::class)
        ->and($log->model_id)->toBe($unit->id);
});

it('dispatches unit.check.recorded webhook', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => ['unit.check.recorded'],
        'is_active' => true,
    ]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    app(RecordUnitCheckAction::class)->handle(
        unit: $unit,
        data: new RecordUnitCheckData(
            result: UnitCheckResult::Ok,
            checkedAt: CarbonImmutable::now(),
        ),
        tenantId: $tenant->id,
    );

    $this->assertDatabaseHas('webhook_deliveries', [
        'tenant_id' => $tenant->id,
        'event' => 'unit.check.recorded',
    ]);
});

it('validates unit check request rules', function () {
    $rules = \App\Http\Requests\Units\RecordUnitCheckRequest::staticRules();

    expect($rules)->toHaveKeys(['result', 'checked_at', 'latitude', 'longitude'])
        ->and($rules['result'])->toContain('required')
        ->and($rules['result'])->toContain('string');
});
