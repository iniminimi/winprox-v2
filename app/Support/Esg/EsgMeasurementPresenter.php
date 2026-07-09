<?php

declare(strict_types=1);

namespace App\Support\Esg;

use App\Enums\EsgIndicatorType;
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
            EsgIndicatorType::Choice => (string) ($measurement->value_string ?? '—'),
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
