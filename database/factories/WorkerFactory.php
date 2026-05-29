<?php

namespace Database\Factories;

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Worker> */
class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'internal_team_id' => InternalTeam::factory(),
            'name' => fake()->name(),
        ];
    }
}
