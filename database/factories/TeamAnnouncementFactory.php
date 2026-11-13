<?php

namespace Database\Factories;

use App\Models\InternalTeam;
use App\Models\TeamAnnouncement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamAnnouncement> */
class TeamAnnouncementFactory extends Factory
{
    protected $model = TeamAnnouncement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'internal_team_id' => InternalTeam::factory(),
            'worker_id' => null,
            'description' => fake()->sentence(),
            'original_language' => 'nl',
            'is_active' => true,
            'published_at' => now()->subMinute(),
            'expires_at' => null,
        ];
    }
}
