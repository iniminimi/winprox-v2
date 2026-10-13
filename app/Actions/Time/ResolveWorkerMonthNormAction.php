<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerMonthNorm;
use App\Enums\HolidayAgreementEffect;
use App\Enums\MonthNormStatus;
use App\Models\BelgianPublicHoliday;
use App\Models\TenantHolidayAgreement;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Support\Time\BelgianHolidayStandardScheme;
use App\Support\Time\BelgianPublicHolidayCalendar;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;

/**
 * Read-only maandnorm. Eerst de normale minuten per kalenderdatum, daarna
 * feestdagafspraken als één geheel. Zonder werkregeling geldt de impliciete
 * ma–vr-aanname: de berekende minuten worden getoond met status `incomplete`.
 * Een feestdag zonder afspraak volgt de afgeleide Belgische standaard
 * (weekdag vervalt; weekend krijgt een voorgestelde vervangingsdag).
 * Een stale patroon of conflict levert geen minuten.
 */
class ResolveWorkerMonthNormAction
{
    /**
     * @param  list<int>  $workerIds
     * @return array<int, WorkerMonthNorm>
     */
    public function handle(int $tenantId, array $workerIds, string $month): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $workerIds = array_values(array_unique(array_map('intval', $workerIds)));
        if ($workerIds === []) {
            return [];
        }

        $timezone = (string) config('app.timezone');
        $start = Carbon::parse($month, $timezone)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }
        $monthStart = $dates[0];
        $monthEnd = $dates[array_key_last($dates)];

        $contracts = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->pluck('weekly_contract_minutes', 'id');

        $patterns = [];
        $patternRows = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $contracts->keys())
            ->get(['worker_id', 'weekday', 'minutes']);
        foreach ($patternRows as $row) {
            $patterns[(int) $row->worker_id][(int) $row->weekday] = (int) $row->minutes;
        }

        $holidayRows = BelgianPublicHoliday::query()->get(['date', 'year', 'code']);
        /** @var array<string, true> $holidayDates */
        $holidayDates = [];
        /** @var array<int, array<string, true>> $codesByYear */
        $codesByYear = [];
        foreach ($holidayRows as $holiday) {
            $date = $holiday->date->toDateString();
            $holidayDates[$date] = true;
            $codesByYear[(int) $holiday->year][$holiday->code] = true;
        }

        $holidaysInMonth = array_values(array_filter(
            array_keys($holidayDates),
            fn (string $date) => $date >= $monthStart && $date <= $monthEnd,
        ));

        // Bedrijfsbrede feestdagafspraken: opgeslagen afwijkingen plus de
        // afgeleide Belgische standaard voor feestdagen zonder afspraak
        // (ma–vr vervalt; za/zo krijgt een voorgestelde vervangingsdag).
        $agreements = BelgianHolidayStandardScheme::effectiveFor(
            TenantHolidayAgreement::query()->where('tenant_id', $tenantId)->get(),
            $holidayRows,
            $holidayDates,
            $monthStart,
            $monthEnd,
        );

        $result = [];
        foreach ($contracts as $workerId => $contractMinutes) {
            $workerId = (int) $workerId;
            $result[$workerId] = $this->resolveWorker(
                $contractMinutes === null ? Worker::DEFAULT_WEEKLY_CONTRACT_MINUTES : (int) $contractMinutes,
                $patterns[$workerId] ?? [],
                $dates,
                $holidaysInMonth,
                $agreements,
                $holidayDates,
                $codesByYear,
                $monthStart,
                $monthEnd,
            );
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $pattern
     * @param  list<string>  $dates
     * @param  list<string>  $holidaysInMonth
     * @param  list<TenantHolidayAgreement>  $agreements
     * @param  array<string, true>  $holidayDates
     * @param  array<int, array<string, true>>  $codesByYear
     */
    private function resolveWorker(
        int $contractMinutes,
        array $pattern,
        array $dates,
        array $holidaysInMonth,
        array $agreements,
        array $holidayDates,
        array $codesByYear,
        string $monthStart,
        string $monthEnd,
    ): WorkerMonthNorm {
        $issues = [];
        $implicit = $pattern === [];
        $effectivePattern = $implicit ? Worker::defaultWeekPattern($contractMinutes) : $pattern;
        if (! $this->patternMatches($effectivePattern, $contractMinutes)) {
            $issues[] = MonthNormStatus::Incomplete;
        }
        if (! $this->yearCovered((int) substr($monthStart, 0, 4), $codesByYear)) {
            $issues[] = MonthNormStatus::Incomplete;
        }

        $byHoliday = [];
        foreach ($agreements as $agreement) {
            $byHoliday[$agreement->holiday_date->toDateString()] = $agreement;
        }
        $replacementOwners = [];
        foreach ($agreements as $agreement) {
            $replacement = $agreement->replacement_date?->toDateString();
            if ($replacement === null) {
                continue;
            }
            $replacementOwners[$replacement][] = $agreement->holiday_date->toDateString();
        }

        $touching = [];
        foreach ($agreements as $agreement) {
            $holidayDate = $agreement->holiday_date->toDateString();
            $replacement = $agreement->replacement_date?->toDateString();
            $inMonth = ($holidayDate >= $monthStart && $holidayDate <= $monthEnd)
                || ($replacement !== null && $replacement >= $monthStart && $replacement <= $monthEnd);
            if (! $inMonth) {
                continue;
            }
            $touching[] = $agreement;
            $issues[] = $this->agreementIssue($agreement, $holidayDates, $codesByYear, $replacementOwners);
        }

        $status = $this->combine($issues);
        if ($status !== MonthNormStatus::Available) {
            return new WorkerMonthNorm($status, null);
        }

        $map = [];
        foreach ($dates as $date) {
            $weekday = (int) Carbon::parse($date)->dayOfWeekIso;
            $map[$date] = $effectivePattern[$weekday];
        }

        $instructions = [];
        foreach ($touching as $agreement) {
            $holidayDate = $agreement->holiday_date->toDateString();
            $effect = $agreement->effect;
            if ($effect === HolidayAgreementEffect::Dropped || $effect === HolidayAgreementEffect::Replaced) {
                if (! $this->instruct($instructions, $holidayDate, 0)) {
                    return new WorkerMonthNorm(MonthNormStatus::Conflict, null);
                }
            }
            if ($effect === HolidayAgreementEffect::Replaced) {
                $replacement = $agreement->replacement_date?->toDateString();
                $moved = $effectivePattern[(int) Carbon::parse($holidayDate)->dayOfWeekIso];
                if ($replacement === null || ! $this->instruct($instructions, $replacement, $moved)) {
                    return new WorkerMonthNorm(MonthNormStatus::Conflict, null);
                }
            }
        }

        // Feestdag zonder afspraak telt als niet gewerkt in de norm.
        foreach ($holidaysInMonth as $holidayDate) {
            if (! isset($byHoliday[$holidayDate]) && ! $this->instruct($instructions, $holidayDate, 0)) {
                return new WorkerMonthNorm(MonthNormStatus::Conflict, null);
            }
        }

        foreach ($instructions as $date => $minutes) {
            if (array_key_exists($date, $map)) {
                $map[$date] = $minutes;
            }
        }

        return new WorkerMonthNorm(
            $implicit ? MonthNormStatus::Incomplete : MonthNormStatus::Available,
            array_sum($map),
        );
    }

    /**
     * @param  array<int, int>  $pattern
     */
    private function patternMatches(array $pattern, int $contractMinutes): bool
    {
        if (count($pattern) !== 7) {
            return false;
        }
        for ($day = 1; $day <= 7; $day++) {
            if (! array_key_exists($day, $pattern)) {
                return false;
            }
        }

        return array_sum($pattern) === $contractMinutes;
    }

    /**
     * @param  array<int, array<string, true>>  $codesByYear
     */
    private function yearCovered(int $year, array $codesByYear): bool
    {
        $codes = $codesByYear[$year] ?? [];
        foreach (BelgianPublicHolidayCalendar::CODES as $code) {
            if (! isset($codes[$code])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, true>  $holidayDates
     * @param  array<int, array<string, true>>  $codesByYear
     * @param  array<string, list<string>>  $replacementOwners
     */
    private function agreementIssue(
        TenantHolidayAgreement $agreement,
        array $holidayDates,
        array $codesByYear,
        array $replacementOwners,
    ): MonthNormStatus {
        $holidayDate = $agreement->holiday_date->toDateString();
        $replacement = $agreement->replacement_date?->toDateString();
        $effect = $agreement->effect;

        if ($effect === HolidayAgreementEffect::Replaced) {
            if ($replacement === null || $replacement === $holidayDate) {
                return MonthNormStatus::Conflict;
            }
            $year = (int) substr($replacement, 0, 4);
            if (! $this->yearCovered($year, $codesByYear)) {
                return MonthNormStatus::Incomplete;
            }
            if (isset($holidayDates[$replacement])) {
                return MonthNormStatus::Conflict;
            }
            if (count($replacementOwners[$replacement] ?? []) > 1) {
                return MonthNormStatus::Conflict;
            }

            return MonthNormStatus::Available;
        }

        if ($replacement !== null) {
            return MonthNormStatus::Conflict;
        }

        return MonthNormStatus::Available;
    }

    /**
     * @param  list<MonthNormStatus>  $issues
     */
    private function combine(array $issues): MonthNormStatus
    {
        if (in_array(MonthNormStatus::Conflict, $issues, true)) {
            return MonthNormStatus::Conflict;
        }
        if (in_array(MonthNormStatus::Incomplete, $issues, true)) {
            return MonthNormStatus::Incomplete;
        }

        return MonthNormStatus::Available;
    }

    /**
     * @param  array<string, int>  $instructions
     */
    private function instruct(array &$instructions, string $date, int $minutes): bool
    {
        if (array_key_exists($date, $instructions)) {
            return false;
        }
        $instructions[$date] = $minutes;

        return true;
    }
}
