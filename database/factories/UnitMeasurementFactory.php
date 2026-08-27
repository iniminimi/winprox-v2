<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UnitMeasurementSource;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitMeasurement> */
class UnitMeasurementFactory extends Factory
{
    protected $model = UnitMeasurement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'location_id' => Location::factory(),
            'unit_measure_field_id' => UnitMeasureField::factory(),
            'worker_id' => null,
            'user_id' => null,
            'source' => UnitMeasurementSource::Portal,
            'value_numeric' => 1000,
            'value_boolean' => null,
            'value_string' => null,
            'recorded_at' => now(),
            'created_at' => now(),
        ];
    }
}
