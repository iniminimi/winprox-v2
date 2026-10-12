<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerMonthFacts;
use App\Enums\ShiftTypeKind;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;

/**
 * Feiten van een kalendermaand voor een lijst uitvoerders.
 * Los van de maandnorm: gepland, gewerkt, afwezigheid en onvolledige registratie.
 */
class SummarizeWorkerMonthFactsAction
{
    /**
     * @param  list<int>  $workerIds
     * @return array<int, WorkerMonthFacts>
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
        $nextMonth = $start->copy()->addMonth();
        $monthStart = $start->toDateString();
        $monthEnd = $start->copy()->endOfMonth()->toDateString();

        $ownedIds = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /** @var array<int, array{planned: int, incomplete: int, full: array<string, array<string, true>>, timed: array<string, array<string, int>>}> $buckets */
        $buckets = [];
        foreach ($ownedIds as $workerId) {
            $buckets[$workerId] = [
                'planned' => 0,
                'incomplete' => 0,
                'full' => [],
                'timed' => [],
            ];
        }
        if ($buckets === []) {
            return [];
        }

        $planned = PlannedShift::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $ownedIds)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
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
            ->where('clock_in_at', '>=', $start)
            ->where('clock_in_at', '<', $nextMonth)
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
            ->where('clock_in_at', '<', $nextMonth)
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

            $result[$workerId] = new WorkerMonthFacts(
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
     * @param  array{planned: int, incomplete: int, full: array<string, array<string, true>>, timed: array<string, array<string, int>>}  $bucket
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
