<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScreenRole;
use App\Models\Screen;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Screen> */
class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'clock_point_id' => null,
            'role' => ScreenRole::Sense,
        ];
    }
}
