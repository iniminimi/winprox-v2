<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'unit_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_active' => true,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addWeek(),
        ];
    }

    /** Verlopen mededeling (niet meer zichtbaar op het portaal). */
    public function expired(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->subWeeks(2),
            'expires_at' => now()->subDay(),
        ]);
    }
}
