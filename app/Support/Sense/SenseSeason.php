<?php

declare(strict_types=1);

namespace App\Support\Sense;

use Carbon\CarbonInterface;

/**
 * Koude jaarhelft: 1 oktober tot en met 30 april.
 * Warme jaarhelft: 1 mei tot en met 30 september.
 * Grens om middernacht, Belgische tijd.
 */
final class SenseSeason
{
    public const WINTER = 'winter';

    public const SUMMER = 'summer';

    public static function current(?CarbonInterface $on = null): string
    {
        $month = (int) ($on ?? now())->timezone('Europe/Brussels')->month;

        return $month >= 5 && $month <= 9 ? self::SUMMER : self::WINTER;
    }
}
