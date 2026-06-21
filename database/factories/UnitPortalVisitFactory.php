<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitPortalVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitPortalVisit> */
class UnitPortalVisitFactory extends Factory
{
    protected $model = UnitPortalVisit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'ip_address' => fake()->ipv4(),
            'visited_at' => now(),
        ];
    }
}
