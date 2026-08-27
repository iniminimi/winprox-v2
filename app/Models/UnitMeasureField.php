<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitMeasureFieldType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitMeasureField extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'unit_of_measure',
        'min_value',
        'max_value',
        'options',
        'is_active',
    ];

    protected $casts = [
        'type' => UnitMeasureFieldType::class,
        'min_value' => 'float',
        'max_value' => 'float',
        'options' => 'array',
        'is_active' => 'boolean',
    ];

    public function measurements(): HasMany
    {
        return $this->hasMany(UnitMeasurement::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_measure_field_unit')
            ->withTimestamps();
    }

    /**
     * @return list<string>
     */
    public function normalizedChoiceOptions(): array
    {
        if (! $this->type->usesOptionList() || ! is_array($this->options)) {
            return [];
        }

        $options = [];
        foreach ($this->options as $option) {
            if (! is_string($option) && ! is_numeric($option)) {
                continue;
            }

            $trimmed = trim((string) $option);
            if ($trimmed !== '') {
                $options[] = $trimmed;
            }
        }

        return $options;
    }

    public function optionValueInUse(string $option): bool
    {
        if (! $this->exists || ! $this->type->usesOptionList()) {
            return false;
        }

        return UnitMeasurement::query()
            ->where('unit_measure_field_id', $this->id)
            ->where('value_string', $option)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function choiceOptionsWithMeasurements(): array
    {
        if (! $this->exists || ! $this->type->usesOptionList()) {
            return [];
        }

        return UnitMeasurement::query()
            ->where('unit_measure_field_id', $this->id)
            ->whereNotNull('value_string')
            ->distinct()
            ->orderBy('value_string')
            ->pluck('value_string')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
    }

    public function hasMeasurements(): bool
    {
        return $this->measurements()->exists();
    }
}
