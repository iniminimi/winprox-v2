<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SensePoint;
use App\Models\SenseReading;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SenseReading> */
class SenseReadingFactory extends Factory
{
    protected $model = SenseReading::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sense_point_id' => SensePoint::factory(),
            'temperature' => 21.5,
            'humidity' => 52,
            'co2' => 800,
            'voc' => 80,
            'recorded_at' => now(),
        ];
    }
}
