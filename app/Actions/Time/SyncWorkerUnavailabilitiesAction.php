<?php

namespace App\Actions\Time;

use App\Models\Worker;
use App\Models\WorkerUnavailability;

/**
 * Synchroniseert de terugkerende weekdag-onbeschikbaarheid van een uitvoerder.
 * Alleen genormaliseerde weekdagen 1 (ma) t/m 7 (zo) worden bewaard.
 */
final class SyncWorkerUnavailabilitiesAction
{
    /**
     * @param  list<int|string>  $weekdays
     */
    public function handle(Worker $worker, array $weekdays): void
    {
        $wanted = array_values(array_unique(array_map(
            'intval',
            array_filter($weekdays, fn ($day) => is_numeric($day)),
        )));
        $wanted = array_values(array_filter($wanted, fn (int $day) => $day >= 1 && $day <= 7));

        $existing = $worker->unavailabilities()->pluck('weekday', 'id')->all();

        $existingWeekdays = array_map('intval', array_values($existing));
        $toDelete = array_diff($existingWeekdays, $wanted);
        $toCreate = array_diff($wanted, $existingWeekdays);

        if ($toDelete !== []) {
            $ids = array_keys(array_filter(
                $existing,
                fn ($weekday) => in_array((int) $weekday, $toDelete, true),
            ));
            $worker->unavailabilities()->whereIn('id', $ids)->delete();
        }

        foreach ($toCreate as $weekday) {
            WorkerUnavailability::query()->create([
                'tenant_id' => $worker->tenant_id,
                'worker_id' => $worker->id,
                'weekday' => $weekday,
            ]);
        }
    }
}
