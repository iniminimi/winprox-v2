<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerUnavailability;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerUnavailabilityFactory extends Factory
{
    protected $model = WorkerUnavailability::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'weekday' => $this->faker->numberBetween(1, 7),
        ];
    }
}
