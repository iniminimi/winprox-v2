<?php

namespace App\Support\Time;

use Carbon\Carbon;

/**
 * Tien wettelijke Belgische feestdagen. Vaste codes, datums per jaar.
 * Paasmaandag, Hemelvaart en pinkstermaandag volgen de paasdatum.
 */
final class BelgianPublicHolidayCalendar
{
    public const FIRST_YEAR = 2020;

    public const LAST_YEAR = 2040;

    /** @var list<string> */
    public const CODES = [
        'new_year',
        'easter_monday',
        'labour_day',
        'ascension',
        'whit_monday',
        'national_day',
        'assumption',
        'all_saints',
        'armistice',
        'christmas',
    ];

    /**
     * @return array<string, string> code => Y-m-d
     */
    public static function datesForYear(int $year): array
    {
        $easter = self::easter($year);

        return [
            'new_year' => sprintf('%04d-01-01', $year),
            'easter_monday' => $easter->copy()->addDay()->toDateString(),
            'labour_day' => sprintf('%04d-05-01', $year),
            'ascension' => $easter->copy()->addDays(39)->toDateString(),
            'whit_monday' => $easter->copy()->addDays(50)->toDateString(),
            'national_day' => sprintf('%04d-07-21', $year),
            'assumption' => sprintf('%04d-08-15', $year),
            'all_saints' => sprintf('%04d-11-01', $year),
            'armistice' => sprintf('%04d-11-11', $year),
            'christmas' => sprintf('%04d-12-25', $year),
        ];
    }

    public static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
