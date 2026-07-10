<?php

namespace Database\Factories;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    protected $model = WorkShift::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'internal_team_id' => InternalTeam::factory(),
            'clock_in_clock_point_id' => ClockPoint::factory(),
            'clock_out_clock_point_id' => null,
            'status' => WorkShiftStatus::Open,
            'clock_in_at' => now(),
            'clock_in_client_at' => null,
            'clock_in_source' => ClockSource::ClockPointQr,
            'clock_in_device_id' => null,
            'clock_out_at' => null,
            'clock_out_client_at' => null,
            'clock_out_source' => null,
            'total_break_minutes' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => WorkShiftStatus::Closed,
            'clock_out_at' => now(),
            'clock_out_source' => ClockSource::ClockPointQr,
        ]);
    }
}
