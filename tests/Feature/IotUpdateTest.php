<?php

declare(strict_types=1);

use App\Actions\Iot\CreateIotGatewayAction;
use App\Actions\Iot\CreateIotRuleAction;
use App\Actions\Iot\CreateIotSensorAction;
use App\Actions\Iot\UpdateIotGatewayAction;
use App\Actions\Iot\UpdateIotRuleAction;
use App\Actions\Iot\UpdateIotSensorAction;
use App\Enums\IotRuleOperator;
use App\Enums\IotSensorType;
use App\Models\IotGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('werkt gateway, sensor en regel bij', function () {
    $tenant = Tenant::factory()->create([
        'has_iot_module' => true,
        'billing_plan' => 'facility',
        'billing_active_until' => now()->addMonth(),
    ]);
    Tenancy::actAs($tenant->id);

    $created = app(CreateIotGatewayAction::class)->handle('Oude naam', (int) $tenant->id);
    $gateway = app(UpdateIotGatewayAction::class)->handle($created['gateway'], 'Nieuwe gateway');
    expect($gateway->name)->toBe('Nieuwe gateway');

    $sensor = app(CreateIotSensorAction::class)->handle([
        'iot_gateway_id' => $gateway->id,
        'external_id' => 'old-id',
        'name' => 'Oude sensor',
        'sensor_type' => IotSensorType::Other->value,
    ], (int) $tenant->id);

    $sensor = app(UpdateIotSensorAction::class)->handle($sensor, [
        'iot_gateway_id' => $gateway->id,
        'external_id' => 'new-id',
        'name' => 'Nieuwe sensor',
        'sensor_type' => IotSensorType::FillLevel->value,
    ], (int) $tenant->id);

    expect($sensor->external_id)->toBe('new-id')
        ->and($sensor->sensor_type)->toBe(IotSensorType::FillLevel);

    $rule = app(CreateIotRuleAction::class)->handle([
        'iot_sensor_id' => $sensor->id,
        'name' => 'Oude regel',
        'operator' => IotRuleOperator::Gte->value,
        'threshold' => 1,
        'description' => 'Oude tekst',
    ], (int) $tenant->id);

    $rule = app(UpdateIotRuleAction::class)->handle($rule, [
        'iot_sensor_id' => $sensor->id,
        'name' => 'Nieuwe regel',
        'operator' => IotRuleOperator::Gt->value,
        'threshold' => 90,
        'description' => 'Nieuwe tekst',
    ], (int) $tenant->id);

    expect($rule->name)->toBe('Nieuwe regel')
        ->and((float) $rule->threshold)->toBe(90.0)
        ->and($rule->operator)->toBe(IotRuleOperator::Gt);

    expect(IotGateway::query()->count())->toBe(1);
});

it('toont de assistant_iot-clip in de IoT-paginakop', function () {
    $tenant = Tenant::factory()->create([
        'has_iot_module' => true,
        'billing_plan' => 'facility',
        'billing_active_until' => now()->addMonth(),
    ]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $this->actingAs($user)
        ->get(route('iot.index'))
        ->assertOk()
        ->assertSee('video/assistant_iot_80.mp4', false)
        ->assertSee('wp-page-icon--assistant', false);
});
