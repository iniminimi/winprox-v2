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

    public static function onScale(SenseMetric $metric, float $value): string
    {
        if ($metric === SenseMetric::Voc || $metric === SenseMetric::Stale) {
            return self::withUnit($metric, $value);
        }

        $range = config('sense.ranges.'.$metric->value);
        $min = (float) ($range['min'] ?? $value);
        $max = (float) ($range['max'] ?? $value);

        if ($value < $min) {
            return self::edge($metric, $min).'-';
        }

        if ($value > $max) {
            return self::edge($metric, $max).'+';
        }

        return self::withUnit($metric, $value);
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

    private static function edge(SenseMetric $metric, float $bound): string
    {
        $number = self::format($bound, 0);

        return $metric === SenseMetric::Humidity ? $number.'%' : $number;
    }
}
