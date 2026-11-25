<?php

namespace App\Support\Time;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Kalender voor de jaaropkuis en het fotobestand.
 * Tijdzone Brussel: 1 februari is de dag van de klant, niet UTC.
 *
 * Op 1 februari 2027 gaat 2025 weg. 2026 blijft het hele jaar 2027 zichtbaar.
 * In januari 2027 blijft ook 2025 nog staan.
 */
final class TimeArchiveWindow
{
    public const TZ = 'Europe/Brussels';

    public static function brussels(?CarbonInterface $now = null): Carbon
    {
        $base = $now instanceof CarbonInterface ? Carbon::instance($now) : now();

        return $base->copy()->timezone(self::TZ);
    }

    public static function latestArchivableYear(?CarbonInterface $now = null): int
    {
        $now = self::brussels($now);
        $year = (int) $now->year;

        if ((int) $now->month < 2) {
            return $year - 3;
        }

        return $year - 2;
    }

    /** Muurklok Brussel, dezelfde vorm als waarin de inklok in de database staat. */
    public static function yearStart(int $year): Carbon
    {
        return Carbon::create($year, 1, 1, 0, 0, 0, self::TZ);
    }

    public static function yearEnd(int $year): Carbon
    {
        return Carbon::create($year + 1, 1, 1, 0, 0, 0, self::TZ);
    }

    /** Maand waarin de foto 6 maanden wordt, als YYYY-MM. */
    public static function photoPeriod(CarbonInterface $createdAt): string
    {
        return $createdAt->copy()
            ->timezone(self::TZ)
            ->addMonths(AbsencePhotoRetention::keepMonths())
            ->format('Y-m');
    }

    /**
     * Fotobestand van maart blijft tot 1 mei (heel april).
     * Een late inhaal blijft minstens een maand beschikbaar vanaf nu.
     */
    public static function photoAvailableUntil(string $period, ?CarbonInterface $now = null): Carbon
    {
        [$year, $month] = array_map('intval', explode('-', $period, 2));
        $endOfFollowingMonth = Carbon::create($year, $month, 1, 0, 0, 0, self::TZ)->addMonths(2);
        $oneMonthFromNow = self::brussels($now)->addMonth()->startOfDay();

        return $endOfFollowingMonth->greaterThan($oneMonthFromNow)
            ? $endOfFollowingMonth
            : $oneMonthFromNow;
    }

    /** Bestand van 2025, gemaakt op 1 februari 2027, blijft tot 1 februari 2030. Een late run krijgt drie jaar. */
    public static function yearAvailableUntil(int $year, ?CarbonInterface $now = null): Carbon
    {
        $scheduled = Carbon::create($year + 5, 2, 1, 0, 0, 0, self::TZ);
        $threeYears = self::brussels($now)->addYears(3)->startOfDay();

        return $scheduled->greaterThan($threeYears) ? $scheduled : $threeYears;
    }
}
