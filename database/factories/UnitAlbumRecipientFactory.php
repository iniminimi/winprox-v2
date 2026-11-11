<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitAlbumRecipient> */
class UnitAlbumRecipientFactory extends Factory
{
    protected $model = UnitAlbumRecipient::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'email' => fake()->unique()->safeEmail(),
            'description' => null,
        ];
    }
}
