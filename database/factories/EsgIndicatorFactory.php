<?php

namespace Database\Factories;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EsgIndicator> */
class EsgIndicatorFactory extends Factory
{
    protected $model = EsgIndicator::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'type' => EsgIndicatorType::Numeric,
            'unit_of_measure' => 'kWh',
            'is_active' => true,
            'thresholds' => null,
        ];
    }

    public function numeric(string $unitOfMeasure = 'kWh'): static
    {
        return $this->state(fn () => [
            'type' => EsgIndicatorType::Numeric,
            'unit_of_measure' => $unitOfMeasure,
        ]);
    }

    public function boolean(): static
    {
        return $this->state(fn () => [
            'type' => EsgIndicatorType::Boolean,
            'unit_of_measure' => null,
        ]);
    }

    public function string(): static
    {
        return $this->state(fn () => [
            'type' => EsgIndicatorType::String,
            'unit_of_measure' => null,
        ]);
    }

    public function json(): static
    {
        return $this->state(fn () => [
            'type' => EsgIndicatorType::Json,
            'unit_of_measure' => null,
        ]);
    }
}
