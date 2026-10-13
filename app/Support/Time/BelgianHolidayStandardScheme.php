<?php

namespace App\Support\Time;

use App\Enums\HolidayAgreementEffect;
use App\Models\BelgianPublicHoliday;
use App\Models\TenantHolidayAgreement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Afgeleide Belgische standaardregeling (ma–vr-werkweek) voor feestdagen
 * zonder tenant-afspraak. Feestdagen op werkdagen vervallen; feestdagen
 * in het weekend krijgen een voorgestelde vervangingsdag — dat is een
 * voorstel voor de normberekening, geen vastgelegde bedrijfsafspraak.
 * `tenant_holiday_agreements` bewaart daarom alleen afwijkingen.
 */
final class BelgianHolidayStandardScheme
{
    /**
     * Effectieve afspraken voor een venster: opgeslagen tenant-afwijkingen
     * winnen; feestdagen zonder afspraak volgen de afgeleide standaard.
     * Alleen feestdagen die het venster kunnen raken (eigen datum of
     * voorgestelde vervangingsdag) worden afgeleid. Voorgestelde data die
     * met opgeslagen afspraken of eerdere voorstellen botsen, schuiven door
     * naar de eerstvolgende vrije werkdag.
     *
     * @param  Collection<int, TenantHolidayAgreement>  $stored
     * @param  Collection<int, BelgianPublicHoliday>  $holidayRows
     * @param  array<string, true>  $holidayDates
     * @return list<TenantHolidayAgreement>
     */
    public static function effectiveFor(
        Collection $stored,
        Collection $holidayRows,
        array $holidayDates,
        string $windowStart,
        string $windowEnd,
    ): array {
        $storedByDate = [];
        $taken = [];
        foreach ($stored as $agreement) {
            $storedByDate[$agreement->holiday_date->toDateString()] = true;
            $replacement = $agreement->replacement_date?->toDateString();
            if ($replacement !== null) {
                $taken[$replacement] = true;
            }
        }

        $effective = $stored->all();
        $firstDerived = Carbon::parse($windowStart)->subDays(15)->toDateString();
        foreach ($holidayRows->sortBy('date') as $holiday) {
            $date = $holiday->date->toDateString();
            if ($date < $firstDerived || $date > $windowEnd || isset($storedByDate[$date])) {
                continue;
            }
            $effect = self::effectFor($date);
            $replacement = $effect === HolidayAgreementEffect::Replaced
                ? self::proposedReplacement($date, $holidayDates, $taken)
                : null;
            if ($effect === HolidayAgreementEffect::Replaced && $replacement === null) {
                continue;
            }
            if ($replacement !== null) {
                $taken[$replacement] = true;
            }
            $effective[] = new TenantHolidayAgreement([
                'holiday_date' => $date,
                'effect' => $effect,
                'replacement_date' => $replacement,
            ]);
        }

        return $effective;
    }

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
