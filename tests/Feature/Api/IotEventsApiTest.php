<?php

declare(strict_types=1);

use App\Enums\IotEventKind;
use App\Enums\IotEventStatus;
use App\Enums\IssueSource;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\IotEvent;
use App\Models\IotGateway;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     gateway: IotGateway,
 *     plain_token: string,
 *     sensor: IotSensor,
 *     rule: IotRule,
 *     team: InternalTeam,
 *     location: Location,
 *     unit: Unit
 * }
 */
function iotAlarmFixture(bool $withEsg = false): array
{
    $tenant = Tenant::factory()->create([
        'has_iot_module' => true,
        'has_esg_module' => $withEsg,
        'billing_plan' => $withEsg ? 'corporate' : 'facility_250',
        'billing_active_until' => now()->addMonth(),
        'is_active' => true,
    ]);

    Tenancy::actAs($tenant->id);

    $plain = 'wpiot_'.str_repeat('a', 40);
    $gateway = IotGateway::factory()->create([
        'tenant_id' => $tenant->id,
        'token_hash' => IotGateway::hashToken($plain),
        'token_prefix' => substr($plain, 0, 12),
        'is_active' => true,
    ]);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $sensor = IotSensor::factory()->create([
        'tenant_id' => $tenant->id,
        'iot_gateway_id' => $gateway->id,
        'external_id' => 'leak-01',
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => null,
    ]);

    $rule = IotRule::factory()->create([
        'tenant_id' => $tenant->id,
        'iot_sensor_id' => $sensor->id,
        'internal_team_id' => $team->id,
        'description' => 'Waterlek gedetecteerd',
        'threshold' => 1,
    ]);

    return compact('tenant', 'gateway', 'plain', 'sensor', 'rule', 'team', 'location', 'unit')
        + ['plain_token' => $plain];
}

it('maakt een melding aan vanuit een IoT-alarm (Facility)', function () {
    $fixture = iotAlarmFixture(false);

    $response = $this->withHeaders([
        'X-WinProx-Iot-Key' => $fixture['plain_token'],
    ])->postJson('/api/v1/iot/events', [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Alarm->value,
        'value' => 1,
        'occurred_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', IotEventStatus::Processed->value)
        ->assertJsonPath('data.kind', IotEventKind::Alarm->value);

    Tenancy::actAs($fixture['tenant']->id);

    $issue = Issue::query()->first();
    expect($issue)->not->toBeNull()
        ->and($issue->source)->toBe(IssueSource::Iot)
        ->and($issue->approved_at)->not->toBeNull()
        ->and($issue->description)->toBe('Waterlek gedetecteerd')
        ->and($issue->tasks)->toHaveCount(1)
        ->and((int) $issue->tasks->first()->internal_team_id)->toBe($fixture['team']->id);
});

it('dedupliceert open IoT-alarmen voor dezelfde regel', function () {
    $fixture = iotAlarmFixture(false);

    $headers = ['X-WinProx-Iot-Key' => $fixture['plain_token']];
    $payload = [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Alarm->value,
        'value' => 1,
        'occurred_at' => now()->toIso8601String(),
    ];

    $this->withHeaders($headers)->postJson('/api/v1/iot/events', $payload)->assertCreated();
    $second = $this->withHeaders($headers)->postJson('/api/v1/iot/events', $payload)->assertCreated();

    $second->assertJsonPath('data.status', IotEventStatus::Deduped->value);

    Tenancy::actAs($fixture['tenant']->id);
    expect(Issue::count())->toBe(1)
        ->and(IotEvent::count())->toBe(2);
});

it('negeert metingen zonder ESG-module (Facility)', function () {
    $fixture = iotAlarmFixture(false);

    $this->withHeaders([
        'X-WinProx-Iot-Key' => $fixture['plain_token'],
    ])->postJson('/api/v1/iot/events', [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Measurement->value,
        'value' => 12.5,
        'occurred_at' => now()->toIso8601String(),
    ])->assertCreated()
        ->assertJsonPath('data.status', IotEventStatus::Ignored->value);

    Tenancy::actAs($fixture['tenant']->id);
    expect(EsgMeasurement::count())->toBe(0)
        ->and(Issue::count())->toBe(0);
});

it('schrijft ESG-meting via IoT (Corporate)', function () {
    $fixture = iotAlarmFixture(true);

    $indicator = EsgIndicator::factory()->numeric('kWh')->create([
        'tenant_id' => $fixture['tenant']->id,
    ]);

    $fixture['sensor']->update([
        'esg_indicator_id' => $indicator->id,
    ]);

    $this->withHeaders([
        'X-WinProx-Iot-Key' => $fixture['plain_token'],
    ])->postJson('/api/v1/iot/events', [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Measurement->value,
        'value' => 42.4,
        'occurred_at' => now()->toIso8601String(),
    ])->assertCreated()
        ->assertJsonPath('data.status', IotEventStatus::Processed->value);

    Tenancy::actAs($fixture['tenant']->id);

    $measurement = EsgMeasurement::query()->first();
    expect($measurement)->not->toBeNull()
        ->and($measurement->task_id)->toBeNull()
        ->and((float) $measurement->value_numeric)->toBe(42.0);
});

it('weigert IoT-ingest zonder geldige gateway-token', function () {
    iotAlarmFixture(false);

    $this->postJson('/api/v1/iot/events', [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Alarm->value,
        'occurred_at' => now()->toIso8601String(),
    ])->assertUnauthorized();
});

it('respecteert idempotency_key op IoT-events', function () {
    $fixture = iotAlarmFixture(false);

    $payload = [
        'external_sensor_id' => 'leak-01',
        'kind' => IotEventKind::Alarm->value,
        'value' => 1,
        'occurred_at' => now()->toIso8601String(),
        'idempotency_key' => 'evt-fixed-1',
    ];

    $headers = ['X-WinProx-Iot-Key' => $fixture['plain_token']];

    $first = $this->withHeaders($headers)->postJson('/api/v1/iot/events', $payload)->assertCreated();
    $second = $this->withHeaders($headers)->postJson('/api/v1/iot/events', $payload)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));

    Tenancy::actAs($fixture['tenant']->id);
    expect(IotEvent::count())->toBe(1)
        ->and(Issue::count())->toBe(1);
});

