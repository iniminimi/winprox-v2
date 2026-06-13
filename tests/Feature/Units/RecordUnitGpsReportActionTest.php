<?php

declare(strict_types=1);

use App\Actions\Units\RecordUnitGpsReportAction;
use App\Data\Units\RecordUnitGpsReportData;
use App\Events\Units\UnitGpsReported;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::forget());

it('records a gps report for a unit', function () {
    Event::fake([UnitGpsReported::class]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    $reportedAt = CarbonImmutable::parse('2026-06-13T14:30:00+02:00');

    $report = app(RecordUnitGpsReportAction::class)->handle(
        unit: $unit,
        data: new RecordUnitGpsReportData(
            latitude: 51.12345678,
            longitude: 4.56789012,
            reportedAt: $reportedAt,
        ),
        tenantId: $tenant->id,
        actorUserId: null,
        workerId: null,
    );

    expect($report)->toBeInstanceOf(UnitGpsReport::class)
        ->and($report->latitude)->toBe(51.12345678)
        ->and($report->longitude)->toBe(4.56789012)
        ->and($report->reported_at->toIso8601String())->toBe($reportedAt->toIso8601String());

    $unit->refresh();
    expect($unit->hasGps())->toBeTrue()
        ->and($unit->googleMapsUrl())->toBe('https://www.google.com/maps/search/?api=1&query=51.12345678,4.56789012');

    Event::assertDispatched(UnitGpsReported::class);
});

it('keeps gps history when a newer report is added', function () {
    Event::fake([UnitGpsReported::class]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => Location::factory()->create(['tenant_id' => $tenant->id])->id,
    ]);

    $action = app(RecordUnitGpsReportAction::class);

    $action->handle(
        $unit,
        new RecordUnitGpsReportData(50.0, 3.0, CarbonImmutable::parse('2026-06-01T10:00:00+02:00')),
        $tenant->id,
    );
    $action->handle(
        $unit,
        new RecordUnitGpsReportData(51.98765432, 4.56789012, CarbonImmutable::parse('2026-06-13T15:00:00+02:00')),
        $tenant->id,
    );

    expect(UnitGpsReport::query()->where('unit_id', $unit->id)->count())->toBe(2);

    $unit->refresh()->load('latestGpsReport');
    expect($unit->latestGpsReport?->latitude)->toBe(51.98765432)
        ->and($unit->latestGpsReport?->longitude)->toBe(4.56789012);
});

it('writes audit log via unit.gps_reported webhook event', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => Location::factory()->create(['tenant_id' => $tenant->id])->id,
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    app(RecordUnitGpsReportAction::class)->handle(
        $unit,
        new RecordUnitGpsReportData(51.0, 4.0, CarbonImmutable::now()),
        $tenant->id,
        $user->id,
    );

    $log = AuditLog::query()->where('action', 'unit.gps_reported')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->model_type)->toBe(Unit::class)
        ->and($log->model_id)->toBe($unit->id);
});

it('dispatches unit.gps_reported webhook', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => ['unit.gps_reported'],
        'is_active' => true,
    ]);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => Location::factory()->create(['tenant_id' => $tenant->id])->id,
    ]);

    app(RecordUnitGpsReportAction::class)->handle(
        $unit,
        new RecordUnitGpsReportData(51.05, 3.72, CarbonImmutable::now()),
        $tenant->id,
    );

    $this->assertDatabaseHas('webhook_deliveries', [
        'tenant_id' => $tenant->id,
        'event' => 'unit.gps_reported',
    ]);
});

it('validates gps report request rules', function () {
    $rules = \App\Http\Requests\Units\RecordUnitGpsReportRequest::staticRules();

    expect($rules)->toHaveKeys(['latitude', 'longitude', 'reported_at'])
        ->and($rules['latitude'])->toContain('between:-90,90')
        ->and($rules['longitude'])->toContain('between:-180,180');
});
