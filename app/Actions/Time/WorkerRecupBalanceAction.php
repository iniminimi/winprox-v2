<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Overuren van dit kalenderjaar tot vandaag, in minuten (kan negatief).
 * Gewerkte minuten van gesloten diensten, min gepubliceerde werkminuten tot vandaag,
 * min gepubliceerde recup en open recupaanvragen op dagen die nog niet als recup gepland zijn.
 * Een hele recupdag volgt het weekpatroon, of de standaardverdeling van het contract.
 * Zonder patroon en zonder contract telt die dag 0 minuten. Geen loon.
 */
class WorkerRecupBalanceAction
{
    public function handle(Tenant $tenant, Worker $worker, ?CarbonImmutable $today = null): int
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $timezone = (string) config('app.timezone');
        $today = ($today ?? CarbonImmutable::now($timezone))->timezone($timezone)->startOfDay();
        $yearStart = $today->startOfYear();
        $tomorrow = $today->addDay();
        $yearEnd = $today->endOfYear()->toDateString();
        $todayDate = $today->toDateString();

        $pattern = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->pluck('minutes', 'weekday')
            ->map(fn ($minutes) => (int) $minutes)
            ->all();

        $plannedWork = 0;
        /** @var array<string, true> $fullRecup */
        $fullRecup = [];
        /** @var array<string, int> $timedRecup */
        $timedRecup = [];

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published)
            ->whereDate('work_date', '>=', $yearStart->toDateString())
            ->whereDate('work_date', '<=', $yearEnd)
            ->get(['work_date', 'kind', 'start_time', 'end_time', 'break_minutes']);

        foreach ($planned as $shift) {
            $date = $shift->work_date->toDateString();
            if ($shift->kind->isWork()) {
                if ($date > $todayDate) {
                    continue;
                }
                $minutes = $this->exactMinutes($shift->start_time, $shift->end_time, (int) $shift->break_minutes);
                if ($minutes !== null) {
                    $plannedWork += $minutes;
                }

                continue;
            }

            if ($shift->kind !== ShiftTypeKind::Recup) {
                continue;
            }

            if (! $this->hasTime($shift->start_time) && ! $this->hasTime($shift->end_time)) {
                $fullRecup[$date] = true;

                continue;
            }

            $minutes = $this->exactMinutes($shift->start_time, $shift->end_time, (int) $shift->break_minutes);
            if ($minutes === null) {
                continue;
            }
            $timedRecup[$date] = ($timedRecup[$date] ?? 0) + $minutes;
        }

        $recupMinutes = 0;
        foreach (array_keys($fullRecup) as $date) {
            $recupMinutes += $this->fullDayMinutes($pattern, $worker->weekly_contract_minutes, $date);
        }
        foreach ($timedRecup as $date => $minutes) {
            if (isset($fullRecup[$date])) {
                continue;
            }
            $recupMinutes += $minutes;
        }

        $requests = AbsenceRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->where('kind', ShiftTypeKind::Recup)
            ->where('status', AbsenceRequestStatus::Pending)
            ->whereDate('date_from', '<=', $yearEnd)
            ->whereDate('date_to', '>=', $yearStart->toDateString())
            ->get(['date_from', 'date_to']);

        foreach ($requests as $request) {
            $from = CarbonImmutable::parse($request->date_from->toDateString())->startOfDay();
            $to = CarbonImmutable::parse($request->date_to->toDateString())->startOfDay();
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $date = $day->toDateString();
                if ($date < $yearStart->toDateString() || $date > $yearEnd) {
                    continue;
                }
                if (isset($fullRecup[$date]) || isset($timedRecup[$date])) {
                    continue;
                }
                $recupMinutes += $this->fullDayMinutes($pattern, $worker->weekly_contract_minutes, $date);
            }
        }

        $worked = 0;
        $shifts = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->whereNotNull('clock_out_at')
            ->where('clock_in_at', '>=', $yearStart)
            ->where('clock_in_at', '<', $tomorrow)
            ->get(['clock_in_at', 'clock_out_at', 'total_break_minutes']);

        foreach ($shifts as $shift) {
            $worked += $shift->netWorkMinutes();
        }

        return $worked - $plannedWork - $recupMinutes;
    }

    /**
     * @param  array<int|string, int>  $pattern
     */
    private function fullDayMinutes(array $pattern, ?int $contractMinutes, string $date): int
    {
        $weekday = CarbonImmutable::parse($date)->isoWeekday();
        if ($pattern !== []) {
            return (int) ($pattern[$weekday] ?? 0);
        }
        if ($contractMinutes !== null && $contractMinutes > 0) {
            return Worker::defaultWeekPattern($contractMinutes)[$weekday];
        }

        return 0;
    }

    private function hasTime(mixed $time): bool
    {
        return is_string($time) && trim($time) !== '';
    }

    private function exactMinutes(mixed $start, mixed $end, int $breakMinutes): ?int
    {
        if (! $this->hasTime($start) || ! $this->hasTime($end)) {
            return null;
        }

        $startMinutes = ShiftType::timeToMinutes((string) $start);
        $endMinutes = ShiftType::timeToMinutes((string) $end);
        if ($endMinutes <= $startMinutes) {
            return null;
        }

        $span = $endMinutes - $startMinutes;
        if ($breakMinutes > $span) {
            return null;
        }

        return $span - $breakMinutes;
    }
}
