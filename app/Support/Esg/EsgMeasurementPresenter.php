<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;

final class EsgMeasurementPresenter
{
    public static function displayValue(EsgMeasurement $measurement): string
    {
        $indicator = $measurement->relationLoaded('indicator')
            ? $measurement->indicator
            : $measurement->indicator()->first();

        if ($indicator === null) {
            return '—';
        }

        return match ($indicator->type) {
            EsgIndicatorType::Numeric => self::formatNumeric($measurement, $indicator->unit_of_measure),
            EsgIndicatorType::Boolean => $measurement->value_boolean
                ? __('esg.portal.boolean_yes')
                : __('esg.portal.boolean_no'),
            EsgIndicatorType::String => (string) ($measurement->value_string ?? '—'),
            EsgIndicatorType::Choice => self::formatChoice($measurement, $indicator),
            EsgIndicatorType::MultiChoice => self::formatMultiChoice($measurement, $indicator),
            EsgIndicatorType::Json => self::formatJson($measurement->value_json),
        };
    }

    public static function isOutsideThresholds(EsgMeasurement $measurement): bool
    {
        $indicator = $measurement->relationLoaded('indicator')
            ? $measurement->indicator
            : $measurement->indicator()->first();

        if ($indicator === null || $indicator->type !== EsgIndicatorType::Numeric) {
            return false;
        }

        $thresholds = $indicator->thresholds;
        if ($thresholds === null || $measurement->value_numeric === null) {
            return false;
        }

        $value = (float) $measurement->value_numeric;

        if (isset($thresholds['min']) && $value < (float) $thresholds['min']) {
            return true;
        }

        if (isset($thresholds['max']) && $value > (float) $thresholds['max']) {
            return true;
        }

        return false;
    }

    private static function formatNumeric(EsgMeasurement $measurement, ?string $unitOfMeasure): string
    {
        if ($measurement->value_numeric === null) {
            return '—';
        }

        $formatted = rtrim(rtrim(number_format((float) $measurement->value_numeric, 4, ',', '.'), '0'), ',');

        return filled($unitOfMeasure) ? "{$formatted} {$unitOfMeasure}" : $formatted;
    }

    private static function formatChoice(EsgMeasurement $measurement, ?EsgIndicator $indicator): string
    {
        $value = $measurement->value_string;
        if ($value === null || $value === '') {
            return '—';
        }

        $options = $indicator?->normalizedChoiceOptions() ?? [];
        if ($options !== [] && ! in_array($value, $options, true)) {
            return __('esg.measurements.legacy_choice_value', ['value' => $value]);
        }

        return $indicator->localizedChoiceOptionLabel($value);
    }

    private static function formatMultiChoice(EsgMeasurement $measurement, ?EsgIndicator $indicator): string
    {
        $values = $measurement->value_json;
        if (! is_array($values) || $values === []) {
            return '—';
        }

        $options = $indicator?->normalizedChoiceOptions() ?? [];
        $parts = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $stringValue = (string) $value;
            if ($options !== [] && ! in_array($stringValue, $options, true)) {
                $parts[] = __('esg.measurements.legacy_choice_value', ['value' => $stringValue]);
            } else {
                $parts[] = $indicator->localizedChoiceOptionLabel($stringValue);
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private static function formatJson(?array $value): string
    {
        if ($value === null || $value === []) {
            return '—';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '—' : $encoded;
    }
}
