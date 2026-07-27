<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IotSensorType;
use App\Models\IotGateway;
use App\Models\IotSensor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IotSensor> */
class IotSensorFactory extends Factory
{
    protected $model = IotSensor::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'iot_gateway_id' => IotGateway::factory(),
            'external_id' => 'sensor-'.fake()->unique()->numerify('###'),
            'name' => 'Sensor '.fake()->word(),
            'sensor_type' => IotSensorType::WaterLeak,
            'location_id' => null,
            'unit_id' => null,
            'esg_indicator_id' => null,
            'is_active' => true,
        ];
    }
}
