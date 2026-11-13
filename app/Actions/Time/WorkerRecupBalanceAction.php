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
 * Zonder patroon en zonder contract telt die dag 0 minuten. Vorige maanden van dit jaar
 * tellen mee; op 1 januari begint het saldo opnieuw. Geen loon.
 */
class WorkerRecupBalanceAction
{
    public function handle(Tenant $tenant, Worker $worker, ?CarbonImmutable $today = null): int
    {
        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $months = $this->monthly($tenant, [(int) $worker->id], $today);

        return array_sum($months[(int) $worker->id] ?? []);
    }

    /**
     * Maanden 1–12 van het peiljaar. De som is het saldo tot vandaag.
     *
     * @param  list<int>  $workerIds
     * @return array<int, array<int, int>>
     */
    public function monthly(Tenant $tenant, array $workerIds, ?CarbonImmutable $today = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $workerIds = array_values(array_unique(array_map('intval', $workerIds)));
        $months = [];
        foreach ($workerIds as $workerId) {
            $months[$workerId] = array_fill(1, 12, 0);
        }
        if ($workerIds === []) {
            return $months;
        }

        $timezone = (string) config('app.timezone');
        $today = ($today ?? CarbonImmutable::now($timezone))->timezone($timezone)->startOfDay();
        $yearStart = $today->startOfYear();
        $tomorrow = $today->addDay();
        $yearEnd = $today->endOfYear()->toDateString();
        $todayDate = $today->toDateString();

        $contracts = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $workerIds)
            ->pluck('weekly_contract_minutes', 'id');

        $patterns = [];
        $patternRows = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->get(['worker_id', 'weekday', 'minutes']);
        foreach ($patternRows as $row) {
            $patterns[(int) $row->worker_id][(int) $row->weekday] = (int) $row->minutes;
        }

        $fullRecup = [];
        $timedRecup = [];

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->where('status', PlannedShiftStatus::Published)
            ->whereDate('work_date', '>=', $yearStart->toDateString())
            ->whereDate('work_date', '<=', $yearEnd)
            ->get(['worker_id', 'work_date', 'kind', 'start_time', 'end_time', 'break_minutes']);

        foreach ($planned as $shift) {
            $workerId = (int) $shift->worker_id;
            if (! isset($months[$workerId])) {
                continue;
            }
            $date = $shift->work_date->toDateString();
            $month = (int) $shift->work_date->month;
            if ($shift->kind->isWork()) {
                if ($date > $todayDate) {
                    continue;
                }
                $minutes = $this->exactMinutes($shift->start_time, $shift->end_time, (int) $shift->break_minutes);
                if ($minutes !== null) {
                    $months[$workerId][$month] -= $minutes;
                }

                continue;
            }

            if ($shift->kind !== ShiftTypeKind::Recup) {
                continue;
            }

            if (! $this->hasTime($shift->start_time) && ! $this->hasTime($shift->end_time)) {
                $fullRecup[$workerId][$date] = true;

                continue;
            }

            $minutes = $this->exactMinutes($shift->start_time, $shift->end_time, (int) $shift->break_minutes);
            if ($minutes === null) {
                continue;
            }
            $timedRecup[$workerId][$date] = ($timedRecup[$workerId][$date] ?? 0) + $minutes;
        }

        foreach ($fullRecup as $workerId => $dates) {
            $pattern = $patterns[$workerId] ?? [];
            $contract = $contracts->has($workerId) ? $contracts[$workerId] : null;
            $contract = $contract === null ? null : (int) $contract;
            foreach (array_keys($dates) as $date) {
                $month = (int) substr($date, 5, 2);
                $months[$workerId][$month] -= $this->fullDayMinutes($pattern, $contract, $date);
            }
        }
        foreach ($timedRecup as $workerId => $dates) {
            foreach ($dates as $date => $minutes) {
                if (isset($fullRecup[$workerId][$date])) {
                    continue;
                }
                $month = (int) substr($date, 5, 2);
                $months[$workerId][$month] -= $minutes;
            }
        }

        $requests = AbsenceRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->where('kind', ShiftTypeKind::Recup)
            ->where('status', AbsenceRequestStatus::Pending)
            ->whereDate('date_from', '<=', $yearEnd)
            ->whereDate('date_to', '>=', $yearStart->toDateString())
            ->get(['worker_id', 'date_from', 'date_to']);

        foreach ($requests as $request) {
            $workerId = (int) $request->worker_id;
            if (! isset($months[$workerId])) {
                continue;
            }
            $pattern = $patterns[$workerId] ?? [];
            $contract = $contracts->has($workerId) ? $contracts[$workerId] : null;
            $contract = $contract === null ? null : (int) $contract;
            $from = CarbonImmutable::parse($request->date_from->toDateString())->startOfDay();
            $to = CarbonImmutable::parse($request->date_to->toDateString())->startOfDay();
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $date = $day->toDateString();
                if ($date < $yearStart->toDateString() || $date > $yearEnd) {
                    continue;
                }
                if (isset($fullRecup[$workerId][$date]) || isset($timedRecup[$workerId][$date])) {
                    continue;
                }
                $months[$workerId][(int) $day->month] -= $this->fullDayMinutes($pattern, $contract, $date);
            }
        }

        $shifts = WorkShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->whereNotNull('clock_out_at')
            ->where('clock_in_at', '>=', $yearStart)
            ->where('clock_in_at', '<', $tomorrow)
            ->get(['worker_id', 'clock_in_at', 'clock_out_at', 'total_break_minutes']);

        foreach ($shifts as $shift) {
            $workerId = (int) $shift->worker_id;
            if (! isset($months[$workerId])) {
                continue;
            }
            $month = (int) $shift->clock_in_at->timezone($timezone)->month;
            $months[$workerId][$month] += $shift->netWorkMinutes();
        }

        return $months;
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
