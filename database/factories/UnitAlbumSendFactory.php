<?php

namespace Database\Factories;

use App\Enums\UnitAlbumSendStatus;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitAlbumSend> */
class UnitAlbumSendFactory extends Factory
{
    protected $model = UnitAlbumSend::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'recipient_email' => fake()->safeEmail(),
            'photo_ids' => [],
            'photo_count' => 0,
            'album_day' => now()->toDateString(),
            'status' => UnitAlbumSendStatus::Queued,
            'scheduled_at' => now(),
        ];
    }
}
