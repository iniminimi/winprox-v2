<?php

namespace Database\Factories;

use App\Enums\PlannedShiftStatus;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannedShift> */
class PlannedShiftFactory extends Factory
{
    protected $model = PlannedShift::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'work_date' => now()->toDateString(),
            'shift_type_id' => null,
            'start_time' => '07:00',
            'end_time' => '15:00',
            'break_minutes' => 0,
            'status' => PlannedShiftStatus::Draft,
        ];
    }
}
