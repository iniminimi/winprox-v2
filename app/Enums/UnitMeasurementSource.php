<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitMeasurementSource: string
{
    case Portal = 'portal';
    case Api = 'api';
    case Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
