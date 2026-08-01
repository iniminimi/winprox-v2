<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitCheckSource: string
{
    case Portal = 'portal';
    case Api = 'api';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
