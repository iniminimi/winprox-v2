<?php

declare(strict_types=1);

namespace App\Support\Sense;

use App\Enums\SenseMetric;
use App\Models\SenseReading;

final class SenseReadingLine
{
    public static function text(SenseReading $reading): string
    {
        $at = $reading->recorded_at?->timezone(config('app.timezone'))->format('d-m-Y H:i') ?? '';

        return $at.' : '
            .__('sense.metrics.temperature').' '.SenseNumber::withUnit(SenseMetric::Temperature, (float) $reading->temperature)
            .' · '.__('sense.metrics.humidity').' '.SenseNumber::withUnit(SenseMetric::Humidity, (float) $reading->humidity)
            .' · '.__('sense.metrics.co2').' '.SenseNumber::withUnit(SenseMetric::Co2, (float) $reading->co2)
            .' · '.__('sense.metrics.voc').' '.SenseNumber::withUnit(SenseMetric::Voc, (float) $reading->voc);
    }
}
