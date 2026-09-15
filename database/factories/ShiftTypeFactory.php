<?php

namespace Database\Factories;

use App\Enums\ShiftTypeColor;
use App\Models\ShiftType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftType> */
class ShiftTypeFactory extends Factory
{
    protected $model = ShiftType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'label' => fake()->words(2, true),
            'start_time' => '07:00',
            'end_time' => '15:00',
            'break_minutes' => 30,
            'color' => ShiftTypeColor::Emerald,
            'is_active' => true,
        ];
    }
}
