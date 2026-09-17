<?php

namespace Database\Factories;

use App\Enums\ClockSource;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Models\WorkVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkVisit> */
class WorkVisitFactory extends Factory
{
    protected $model = WorkVisit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'work_shift_id' => WorkShift::factory(),
            'unit_id' => Unit::factory(),
            'location_id' => Location::factory(),
            'started_at' => now(),
            'start_latitude' => 51.05,
            'start_longitude' => 3.72,
            'clock_source' => ClockSource::Gps,
        ];
    }
}
