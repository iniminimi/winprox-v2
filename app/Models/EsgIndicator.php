<?php

namespace App\Models;

use App\Enums\EsgIndicatorType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsgIndicator extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'unit_of_measure',
        'is_active',
        'thresholds',
        'options',
    ];

    protected $casts = [
        'type' => EsgIndicatorType::class,
        'is_active' => 'boolean',
        'thresholds' => 'array',
        'options' => 'array',
    ];

    public function measurements(): HasMany
    {
        return $this->hasMany(EsgMeasurement::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
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

        if ($this->type === EsgIndicatorType::Choice) {
            return EsgMeasurement::query()
                ->where('esg_indicator_id', $this->id)
                ->where('value_string', $option)
                ->exists();
        }

        foreach ($this->measurements()->whereNotNull('value_json')->pluck('value_json') as $valueJson) {
            if (is_array($valueJson) && in_array($option, $valueJson, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keuze-opties die al in minstens één meting voorkomen (niet verwijderbaar in beheer).
     *
     * @return list<string>
     */
    public function choiceOptionsWithMeasurements(): array
    {
        if (! $this->exists || ! $this->type->usesOptionList()) {
            return [];
        }

        if ($this->type === EsgIndicatorType::Choice) {
            return EsgMeasurement::query()
                ->where('esg_indicator_id', $this->id)
                ->whereNotNull('value_string')
                ->distinct()
                ->orderBy('value_string')
                ->pluck('value_string')
                ->map(fn (mixed $value): string => (string) $value)
                ->all();
        }

        $inUse = [];
        foreach ($this->measurements()->whereNotNull('value_json')->pluck('value_json') as $valueJson) {
            if (! is_array($valueJson)) {
                continue;
            }

            foreach ($valueJson as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $trimmed = trim((string) $item);
                    if ($trimmed !== '') {
                        $inUse[$trimmed] = true;
                    }
                }
            }
        }

        $options = array_keys($inUse);
        sort($options);

        return $options;
    }
}
