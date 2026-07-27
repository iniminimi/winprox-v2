<?php

namespace Database\Factories;

use App\Models\InternalTeam;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InternalTeam> */
class InternalTeamFactory extends Factory
{
    protected $model = InternalTeam::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Technische dienst', 'Schoonmaak', 'Onderhoud', 'Logistiek']),
            'original_language' => 'nl',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
