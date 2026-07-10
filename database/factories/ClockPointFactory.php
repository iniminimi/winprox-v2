<?php

namespace Database\Factories;

use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockPoint>
 */
class ClockPointFactory extends Factory
{
    protected $model = ClockPoint::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => null,
            'name' => fake()->words(2, true),
            'qr_token' => Str::lower(Str::random(40)),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function forLocation(Location $location): static
    {
        return $this->state(fn () => [
            'tenant_id' => $location->tenant_id,
            'location_id' => $location->id,
        ]);
    }
}
