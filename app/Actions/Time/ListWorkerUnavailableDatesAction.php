<?php

namespace App\Actions\Time;

use App\Models\WorkerUnavailability;
use Carbon\CarbonImmutable;

/**
 * Geeft per uitvoerder de datums terug waarop die structureel onbeschikbaar is
 * (terugkerende weekdag-onbeschikbaarheid uit worker_unavailabilities).
 *
 * @return array<int, array<string, true>> map worker_id => [Y-m-d => true]
 */
final class ListWorkerUnavailableDatesAction
{
    /**
     * @param  list<int>  $workerIds
     * @param  string  $from  Y-m-d
     * @param  string  $to  Y-m-d
     * @return array<int, array<string, true>>
     */
    public function handle(int $tenantId, array $workerIds, string $from, string $to): array
    {
        if ($workerIds === []) {
            return [];
        }

        $weekdays = WorkerUnavailability::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds)
            ->get(['worker_id', 'weekday'])
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->pluck('weekday')->map(fn ($w) => (int) $w)->all());

        if ($weekdays->isEmpty()) {
            return [];
        }

        $map = [];
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lte($end)) {
            $iso = $cursor->dayOfWeekIso;
            $date = $cursor->toDateString();
            foreach ($weekdays as $workerId => $days) {
                if (in_array($iso, $days, true)) {
                    $map[$workerId][$date] = true;
                }
            }
            $cursor = $cursor->addDay();
        }

        return $map;
    }
}
