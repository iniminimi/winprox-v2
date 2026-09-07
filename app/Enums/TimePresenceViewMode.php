<?php

namespace App\Enums;

enum TimePresenceViewMode: string
{
    case Board = 'board';
    case Teams = 'teams';
    case Locations = 'locations';

    public static function tryFromRequest(?string $value): self
    {
        if ($value === 'cards') {
            return self::Board;
        }

        return self::tryFrom((string) $value) ?? self::Board;
    }
}
