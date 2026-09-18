<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UnitCheck;
use App\Models\UnitCheckPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitCheckPhoto> */
class UnitCheckPhotoFactory extends Factory
{
    protected $model = UnitCheckPhoto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_check_id' => UnitCheck::factory(),
            'path' => 'unit-check-photos/'.fake()->uuid().'.jpg',
        ];
    }
}
