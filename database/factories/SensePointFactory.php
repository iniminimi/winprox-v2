<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SensePoint;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SensePoint> */
class SensePointFactory extends Factory
{
    protected $model = SensePoint::class;

    public function definition(): array
    {
        $token = SensePoint::issueToken();
        $defaults = config('sense.defaults');

        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'temperature_low' => $defaults['temperature']['low'],
            'temperature_target' => $defaults['temperature']['target'],
            'temperature_high' => $defaults['temperature']['high'],
            'humidity_low' => $defaults['humidity']['low'],
            'humidity_target' => $defaults['humidity']['target'],
            'humidity_high' => $defaults['humidity']['high'],
            'co2_high' => $defaults['co2_high'],
            'voc_high' => $defaults['voc_high'],
        ];
    }
}
