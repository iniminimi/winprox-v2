<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerWeekHours;
use App\Enums\ShiftTypeKind;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;

/**
 * Eén berekening per week voor een lijst uitvoerders.
 * Leest PlannedShift, gesloten WorkShift en nog open WorkShift in drie queries.
 */
class SummarizeWorkerWeekHoursAction
{
    /**
     * @param  list<int>  $workerIds
     * @return array<int, WorkerWeekHours>
     */
    public function handle(int $tenantId, array $workerIds, string $weekStart): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $workerIds = array_values(array_unique(array_map('intval', $workerIds)));
        if ($workerIds === []) {
            return [];
        }

        $timezone = (string) config('app.timezone');
        $monday = Carbon::parse($weekStart, $timezone)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $nextMonday = $monday->copy()->addWeek();
        $sunday = $monday->copy()->addDays(6)->toDateString();
        $mondayDate = $monday->toDateString();

        $contracts = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->pluck('weekly_contract_minutes', 'id');

        /** @var array<int, array{planned: int, incomplete: int, full: array<string, array<string, true>>, timed: array<string, array<string, int>>}> $buckets */
        $buckets = [];
        foreach ($contracts as $workerId => $minutes) {
            $buckets[(int) $workerId] = [
                'planned' => 0,
                'incomplete' => 0,
                'full' => [],
                'timed' => [],
                'contract' => $minutes === null ? null : (int) $minutes,
            ];
        }

        if ($buckets === []) {
            return [];
        }

        $ownedIds = array_keys($buckets);

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $ownedIds)
            ->whereBetween('work_date', [$mondayDate, $sunday])
            ->get(['worker_id', 'work_date', 'kind', 'start_time', 'end_time', 'break_minutes']);

        foreach ($planned as $shift) {
            $workerId = (int) $shift->worker_id;
            if (! isset($buckets[$workerId])) {
                continue;
            }
            $this->accumulatePlanned($buckets[$workerId], $shift);
        }

        $closed = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $ownedIds)
            ->where('clock_in_at', '>=', $monday)
            ->where('clock_in_at', '<', $nextMonday)
            ->whereNotNull('clock_out_at')
            ->get();

        $worked = [];
        foreach ($closed as $shift) {
            $workerId = (int) $shift->worker_id;
            $worked[$workerId] = ($worked[$workerId] ?? 0) + $shift->netWorkMinutes();
        }

        $openWorkerIds = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $ownedIds)
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '<', $nextMonday)
            ->pluck('worker_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();
        $openSet = array_fill_keys($openWorkerIds, true);

        $result = [];
        foreach ($buckets as $workerId => $bucket) {
            $absences = [];
            foreach ([ShiftTypeKind::Leave, ShiftTypeKind::Recup, ShiftTypeKind::Sick] as $kind) {
                $key = $kind->value;
                $days = count($bucket['full'][$key] ?? []);
                $minutes = 0;
                foreach ($bucket['timed'][$key] ?? [] as $date => $timedMinutes) {
                    if (isset($bucket['full'][$key][$date])) {
                        continue;
                    }
                    $minutes += $timedMinutes;
                }
                if ($days === 0 && $minutes === 0) {
                    continue;
                }
                $absences[$key] = ['days' => $days, 'minutes' => $minutes];
            }

            $result[$workerId] = new WorkerWeekHours(
                contractMinutes: $bucket['contract'],
                plannedMinutes: $bucket['planned'],
                workedMinutes: $worked[$workerId] ?? 0,
                registrationIncomplete: isset($openSet[$workerId]),
                incompleteBlocks: $bucket['incomplete'],
                absences: $absences,
            );
        }

        return $result;
    }

    /**
     * @param  array{planned: int, incomplete: int, full: array<string, array<string, true>>, timed: array<string, array<string, int>>, contract: ?int}  $bucket
     */
    private function accumulatePlanned(array &$bucket, PlannedShift $shift): void
    {
        $date = $shift->work_date->toDateString();
        $hasStart = $this->hasTime($shift->start_time);
        $hasEnd = $this->hasTime($shift->end_time);

        if ($shift->kind->isWork()) {
            $minutes = ($hasStart && $hasEnd)
                ? $this->exactMinutes((string) $shift->start_time, (string) $shift->end_time, (int) $shift->break_minutes)
                : null;
            if ($minutes === null) {
                $bucket['incomplete']++;

                return;
            }
            $bucket['planned'] += $minutes;

            return;
        }

        if (! $shift->kind->isAbsence()) {
            return;
        }

        $kind = $shift->kind->value;
        if (! $hasStart && ! $hasEnd) {
            $bucket['full'][$kind][$date] = true;

            return;
        }

        if ($hasStart && $hasEnd) {
            $minutes = $this->exactMinutes((string) $shift->start_time, (string) $shift->end_time, (int) $shift->break_minutes);
            if ($minutes === null) {
                $bucket['incomplete']++;

                return;
            }
            $bucket['timed'][$kind][$date] = ($bucket['timed'][$kind][$date] ?? 0) + $minutes;

            return;
        }

        $bucket['incomplete']++;
    }

    private function hasTime(mixed $time): bool
    {
        return is_string($time) && trim($time) !== '';
    }

    private function exactMinutes(string $start, string $end, int $breakMinutes): ?int
    {
        $startMinutes = ShiftType::timeToMinutes($start);
        $endMinutes = ShiftType::timeToMinutes($end);
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
