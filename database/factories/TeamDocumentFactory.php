<?php

namespace Database\Factories;

use App\Models\InternalTeam;
use App\Models\TeamDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamDocument> */
class TeamDocumentFactory extends Factory
{
    protected $model = TeamDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'internal_team_id' => InternalTeam::factory(),
            'worker_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'original_language' => 'nl',
            'file_path' => 'team-documents/example.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1200,
            'is_active' => true,
            'published_at' => now()->subMinute(),
        ];
    }
}
