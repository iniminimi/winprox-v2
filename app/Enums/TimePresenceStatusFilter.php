<?php

namespace App\Enums;

enum TimePresenceStatusFilter: string
{
    case All = 'all';
    case Active = 'active';
    case Break = 'break';
    case Absent = 'absent';
    case Attention = 'attention';

    public static function tryFromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }
}
