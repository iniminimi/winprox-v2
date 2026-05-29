<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'name' => fake()->bothify('Asset ##??'),
        ];
    }
}
