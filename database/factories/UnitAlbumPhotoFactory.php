<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitAlbumPhoto> */
class UnitAlbumPhotoFactory extends Factory
{
    protected $model = UnitAlbumPhoto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'path' => 'unit-album/'.fake()->uuid().'.jpg',
            'taken_on' => now()->toDateString(),
        ];
    }
}
