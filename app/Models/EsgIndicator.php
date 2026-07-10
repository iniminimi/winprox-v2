<?php

namespace App\Models;

use App\Enums\EsgIndicatorType;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsgIndicator extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'original_language',
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

    public function translations(): HasMany
    {
        return $this->hasMany(EsgIndicatorTranslation::class);
    }

    public function normalizedOriginalLanguage(): string
    {
        return LocaleSupport::normalize($this->original_language);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());
        $name = (string) $this->name;

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $name;
        }

        $row = $this->findCompletedTranslation($locale);

        if ($row instanceof EsgIndicatorTranslation && filled($row->name)) {
            return (string) $row->name;
        }

        return $name;
    }

    public function localizedChoiceOptionLabel(string $canonicalOption, ?string $locale = null): string
    {
        $locale = LocaleSupport::normalize($locale ?? app()->getLocale());

        if ($locale === $this->normalizedOriginalLanguage()) {
            return $canonicalOption;
        }

        $row = $this->findCompletedTranslation($locale);

        if (! $row instanceof EsgIndicatorTranslation || ! is_array($row->options)) {
            return $canonicalOption;
        }

        $sourceOptions = $this->normalizedChoiceOptions();
        $index = array_search($canonicalOption, $sourceOptions, true);

        if ($index === false) {
            return $canonicalOption;
        }

        $translated = $row->options[$index] ?? null;

        return filled($translated) ? (string) $translated : $canonicalOption;
    }

    private function findCompletedTranslation(string $locale): ?EsgIndicatorTranslation
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->first(fn (EsgIndicatorTranslation $row): bool => $row->locale === $locale
                    && $row->status === EsgIndicatorTranslationStatus::Completed);
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('status', EsgIndicatorTranslationStatus::Completed)
            ->first();
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
