<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'issue_id' => Issue::factory(),
            'status' => TaskStatus::New,
            'priority' => TaskPriority::Prio3,
        ];
    }
}
