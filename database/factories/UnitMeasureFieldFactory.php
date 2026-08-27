<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UnitMeasureFieldType;
use App\Models\Tenant;
use App\Models\UnitMeasureField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitMeasureField> */
class UnitMeasureFieldFactory extends Factory
{
    protected $model = UnitMeasureField::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => UnitMeasureFieldType::Numeric,
            'unit_of_measure' => 'km',
            'min_value' => null,
            'max_value' => null,
            'options' => null,
            'is_active' => true,
        ];
    }

    public function numeric(string $unitOfMeasure = 'km'): static
    {
        return $this->state(fn () => [
            'type' => UnitMeasureFieldType::Numeric,
            'unit_of_measure' => $unitOfMeasure,
            'options' => null,
        ]);
    }

    public function boolean(): static
    {
        return $this->state(fn () => [
            'type' => UnitMeasureFieldType::Boolean,
            'unit_of_measure' => null,
            'options' => null,
        ]);
    }

    public function string(): static
    {
        return $this->state(fn () => [
            'type' => UnitMeasureFieldType::String,
            'unit_of_measure' => null,
            'options' => null,
        ]);
    }

    /**
     * @param  list<string>  $options
     */
    public function choice(array $options = ['Beschikbaar', 'In gebruik', 'Defect']): static
    {
        return $this->state(fn () => [
            'type' => UnitMeasureFieldType::Choice,
            'unit_of_measure' => null,
            'options' => $options,
        ]);
    }
}
