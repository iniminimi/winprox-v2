<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkerDevice> */
class WorkerDeviceFactory extends Factory
{
    protected $model = WorkerDevice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'device_token' => WorkerDevice::generateToken(),
            'last_seen_at' => now(),
        ];
    }
}
