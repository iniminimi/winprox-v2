<?php

declare(strict_types=1);

namespace App\Support\Sense;

use App\Enums\SenseMetric;
use App\Models\SenseReading;
use Illuminate\Support\HtmlString;

final class SenseReadingLine
{
    public static function text(SenseReading $reading): string
    {
        $text = '';
        foreach (self::parts($reading) as $part) {
            $text .= $part['text'];
        }

        return $text;
    }

    public static function html(SenseReading $reading): HtmlString
    {
        $html = '';
        foreach (self::parts($reading) as $part) {
            $text = e($part['text']);
            $html .= $part['alarm']
                ? '<strong class="wp-sense-reading--alarm">'.$text.'</strong>'
                : $text;
        }

        return new HtmlString($html);
    }

    /**
     * @return list<array{text: string, alarm: bool}>
     */
    public static function parts(SenseReading $reading): array
    {
        $at = $reading->recorded_at?->timezone(config('app.timezone'))->format('d-m-Y H:i') ?? '';
        $parts = [self::plain($at.' : ')];
        $metrics = [SenseMetric::Temperature, SenseMetric::Humidity, SenseMetric::Co2, SenseMetric::Voc];

        foreach ($metrics as $index => $metric) {
            if ($index > 0) {
                $parts[] = self::plain(' · ');
            }

            $parts[] = self::plain(__($metric->labelKey()).' ');
            $parts[] = [
                'text' => SenseNumber::format(self::value($reading, $metric), $metric === SenseMetric::Temperature ? 1 : 0),
                'alarm' => $reading->point !== null && SenseBands::metricOutside($reading, $reading->point, $metric),
            ];

            $unit = match ($metric) {
                SenseMetric::Temperature => ' °C',
                SenseMetric::Humidity => ' %',
                SenseMetric::Co2 => ' ppm',
                SenseMetric::Voc, SenseMetric::Stale => '',
            };
            if ($unit !== '') {
                $parts[] = self::plain($unit);
            }
        }

        return $parts;
    }

    private static function value(SenseReading $reading, SenseMetric $metric): float
    {
        return match ($metric) {
            SenseMetric::Temperature => (float) $reading->temperature,
            SenseMetric::Humidity => (float) $reading->humidity,
            SenseMetric::Co2 => (float) $reading->co2,
            SenseMetric::Voc => (float) $reading->voc,
            SenseMetric::Stale => 0.0,
        };
    }

    /**
     * @return array{text: string, alarm: bool}
     */
    private static function plain(string $text): array
    {
        return ['text' => $text, 'alarm' => false];
    }
}
