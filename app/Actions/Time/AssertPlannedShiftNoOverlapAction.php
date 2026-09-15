<?php

namespace App\Actions\Time;

use App\Exceptions\RosterValidationException;
use App\Models\ShiftType;

class AssertPlannedShiftNoOverlapAction
{
    /**
     * Half-open interval [start, end): touching times (07:00-11:00 and 11:00-15:00) do not overlap.
     *
     * @param  list<array{worker_id: int, date: string, start: string, end: string}>  $intervals
     */
    public function handle(array $intervals): void
    {
        $byWorkerDate = [];
        foreach ($intervals as $interval) {
            $key = $interval['worker_id'].'|'.$interval['date'];
            $byWorkerDate[$key][] = $interval;
        }

        foreach ($byWorkerDate as $group) {
            $count = count($group);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($this->overlaps($group[$i], $group[$j])) {
                        throw new RosterValidationException(
                            'time.schedule.errors.overlap',
                            workerId: (int) $group[$i]['worker_id'],
                            date: (string) $group[$i]['date'],
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array{start: string, end: string}  $a
     * @param  array{start: string, end: string}  $b
     */
    public function overlaps(array $a, array $b): bool
    {
        $aStart = ShiftType::timeToMinutes($a['start']);
        $aEnd = ShiftType::timeToMinutes($a['end']);
        $bStart = ShiftType::timeToMinutes($b['start']);
        $bEnd = ShiftType::timeToMinutes($b['end']);

        return $aStart < $bEnd && $bStart < $aEnd;
    }
}
