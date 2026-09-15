<?php

namespace App\Actions\Time;

use App\Enums\ShiftTypeKind;
use App\Exceptions\RosterValidationException;
use App\Models\ShiftType;

class AssertPlannedShiftNoOverlapAction
{
    /**
     * Half-open interval [start, end): touching times (07:00-11:00 and 11:00-15:00) do not overlap.
     * Absence occupies the whole calendar day and blocks any other row that day.
     *
     * @param  list<array{worker_id: int, date: string, start: ?string, end: ?string, kind?: string}>  $intervals
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
     * @param  array{start: ?string, end: ?string, kind?: string}  $a
     * @param  array{start: ?string, end: ?string, kind?: string}  $b
     */
    public function overlaps(array $a, array $b): bool
    {
        if ($this->isAbsence($a) || $this->isAbsence($b)) {
            return true;
        }

        $aStart = ShiftType::timeToMinutes((string) $a['start']);
        $aEnd = ShiftType::timeToMinutes((string) $a['end']);
        $bStart = ShiftType::timeToMinutes((string) $b['start']);
        $bEnd = ShiftType::timeToMinutes((string) $b['end']);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * @param  array{start: ?string, end: ?string, kind?: string}  $interval
     */
    private function isAbsence(array $interval): bool
    {
        $kind = ShiftTypeKind::tryFrom((string) ($interval['kind'] ?? ShiftTypeKind::Work->value));

        return $kind?->isAbsence() ?? false;
    }
}
