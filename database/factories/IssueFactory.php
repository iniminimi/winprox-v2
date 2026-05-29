<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'description' => fake()->sentence(),
            'status' => TaskStatus::New,
        ];
    }
}
