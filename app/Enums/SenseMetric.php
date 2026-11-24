<?php

declare(strict_types=1);

namespace App\Enums;

enum SenseMetric: string
{
    case Temperature = 'temperature';
    case Humidity = 'humidity';
    case Co2 = 'co2';
    case Voc = 'voc';
    case Stale = 'stale';

    public function labelKey(): string
    {
        return 'sense.metrics.'.$this->value;
    }

    public function isCeiling(): bool
    {
        return $this === self::Co2 || $this === self::Voc;
    }
}
