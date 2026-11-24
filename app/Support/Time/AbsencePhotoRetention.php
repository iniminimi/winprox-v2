<?php

namespace App\Support\Time;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Foto's bij een afwezigheidsaanvraag blijven 6 maanden. Vanaf 5 maanden
 * waarschuwt het beginscherm, zodat de organisatie zelf een kopie kan bewaren.
 */
final class AbsencePhotoRetention
{
    public static function keepMonths(): int
    {
        return max(1, (int) config('data_retention.absence_photos_months', 6));
    }

    public static function warnMonths(): int
    {
        $warn = (int) config('data_retention.absence_photos_warn_months', 5);

        return max(1, min($warn, self::keepMonths() - 1));
    }

    public static function dropBefore(?CarbonInterface $now = null): Carbon
    {
        $base = $now instanceof CarbonInterface ? Carbon::instance($now) : now();

        return $base->copy()->subMonths(self::keepMonths());
    }

    public static function warnBefore(?CarbonInterface $now = null): Carbon
    {
        $base = $now instanceof CarbonInterface ? Carbon::instance($now) : now();

        return $base->copy()->subMonths(self::warnMonths());
    }
}
