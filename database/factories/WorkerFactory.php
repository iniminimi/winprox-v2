<?php

namespace Database\Factories;

use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\WorkerIcon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Worker> */
class WorkerFactory extends Factory
{
    protected $model = Worker::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'internal_team_id' => InternalTeam::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'field_icon_slug' => fake()->randomElement(WorkerIcon::SLUGS),
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
            'is_active' => true,
        ];
    }

    /** Worker zonder bevestigd icoon (admin voegde enkel een naam toe). */
    public function claimable(): static
    {
        return $this->state(fn () => ['field_icon_slug' => null]);
    }

    public function withIcon(string $slug): static
    {
        return $this->state(fn () => ['field_icon_slug' => $slug]);
    }
}
