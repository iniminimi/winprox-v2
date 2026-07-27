<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IotRuleOperator;
use App\Enums\TaskPriority;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IotRule> */
class IotRuleFactory extends Factory
{
    protected $model = IotRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'iot_sensor_id' => IotSensor::factory(),
            'name' => 'Rule '.fake()->word(),
            'operator' => IotRuleOperator::Gte,
            'threshold' => 1,
            'internal_team_id' => null,
            'priority' => TaskPriority::Prio2,
            'description' => 'IoT alarm detected',
            'is_active' => true,
        ];
    }
}
