<?php

namespace App\Support\Time;

use App\Enums\HolidayAgreementEffect;
use Carbon\Carbon;

/**
 * Afgeleide Belgische standaardregeling (ma–vr-werkweek) voor feestdagen
 * zonder tenant-afspraak. Feestdagen op werkdagen vervallen; feestdagen
 * in het weekend krijgen een voorgestelde vervangingsdag — dat is een
 * voorstel voor de normberekening, geen vastgelegde bedrijfsafspraak.
 * `tenant_holiday_agreements` bewaart daarom alleen afwijkingen.
 */
final class BelgianHolidayStandardScheme
{
    public static function effectFor(string $holidayDate): HolidayAgreementEffect
    {
        return Carbon::parse($holidayDate)->dayOfWeekIso <= 5
            ? HolidayAgreementEffect::Dropped
            : HolidayAgreementEffect::Replaced;
    }

    /**
     * Voorgestelde vervangingsdag voor een weekendfeestdag: de eerstvolgende
     * gewone werkdag (ma–vr) die geen feestdag is en nog niet ingenomen is
     * door een andere afspraak of voorgestelde datum.
     *
     * @param  array<string, true>  $holidayDates
     * @param  array<string, true>  $takenDates
     */
    public static function proposedReplacement(
        string $holidayDate,
        array $holidayDates,
        array $takenDates,
    ): ?string {
        $day = Carbon::parse($holidayDate);
        for ($i = 0; $i < 14; $i++) {
            $day->addDay();
            $candidate = $day->toDateString();
            if ($day->year > BelgianPublicHolidayCalendar::LAST_YEAR) {
                return null;
            }
            if ($day->dayOfWeekIso > 5 || isset($holidayDates[$candidate]) || isset($takenDates[$candidate])) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}
