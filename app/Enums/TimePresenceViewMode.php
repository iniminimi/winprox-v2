<?php

namespace App\Enums;

enum TimePresenceViewMode: string
{
    case Teams = 'teams';
    case Cards = 'cards';
    case Locations = 'locations';

    public static function tryFromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Teams;
    }
}
