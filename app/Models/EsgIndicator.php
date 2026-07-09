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
        if ($this->type !== EsgIndicatorType::Choice || ! is_array($this->options)) {
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

    /**
     * Choice-opties die al in minstens één meting voorkomen (niet verwijderbaar in beheer).
     *
     * @return list<string>
     */
    public function choiceOptionsWithMeasurements(): array
    {
        if ($this->type !== EsgIndicatorType::Choice || ! $this->exists) {
            return [];
        }

        return EsgMeasurement::query()
            ->where('esg_indicator_id', $this->id)
            ->whereNotNull('value_string')
            ->distinct()
            ->orderBy('value_string')
            ->pluck('value_string')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
    }
}
