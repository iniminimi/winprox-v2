<?php

declare(strict_types=1);

namespace App\Support\Sense;

use App\Enums\SenseMetric;

final class SenseNumber
{
    public static function format(float $value, int $decimals): string
    {
        $rounded = round($value, $decimals);
        if (abs($rounded - round($rounded)) < 0.001) {
            $decimals = 0;
        }

        if (class_exists(\NumberFormatter::class)) {
            $locale = str_replace('_', '-', app()->getLocale());
            $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            $formatted = $fmt->format($rounded);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        $decimal = app()->getLocale() === 'en' ? '.' : ',';
        $thousand = $decimal === '.' ? ',' : '.';

        return number_format($rounded, $decimals, $decimal, $thousand);
    }

    public static function withUnit(SenseMetric $metric, float $value): string
    {
        $decimals = $metric === SenseMetric::Temperature ? 1 : 0;
        $number = self::format($value, $decimals);

        return match ($metric) {
            SenseMetric::Temperature => $number.' °C',
            SenseMetric::Humidity => $number.' %',
            SenseMetric::Co2 => $number.' ppm',
            SenseMetric::Voc => $number,
            SenseMetric::Stale => $number,
        };
    }
}
