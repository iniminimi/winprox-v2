<?php

namespace Database\Factories;

use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkerNotification> */
class WorkerNotificationFactory extends Factory
{
    protected $model = WorkerNotification::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'type' => WorkerNotificationType::RosterPublished,
            'reference_id' => now()->startOfWeek()->toDateString(),
            'read_at' => null,
        ];
    }
}
