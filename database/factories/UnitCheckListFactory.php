<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UnitCheckList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitCheckList> */
class UnitCheckListFactory extends Factory
{
    protected $model = UnitCheckList::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'original_language' => 'nl',
            'is_active' => true,
        ];
    }
}
