<?php

declare(strict_types=1);

namespace App\Enums;

enum IotEventKind: string
{
    case Alarm = 'alarm';
    case Measurement = 'measurement';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
