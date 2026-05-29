<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'unit_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => fake()->numberBetween(10_000, 5_000_000),
            'is_public' => true,
            'requires_verification' => false,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ];
    }

    /** Document dat verificatie vereist (geen publieke downloadlink). */
    public function requiresVerification(): static
    {
        return $this->state(fn () => ['requires_verification' => true]);
    }
}
