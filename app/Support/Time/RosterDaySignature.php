<?php

namespace App\Support\Time;

use App\Models\PlannedShift;
use App\Models\ShiftType;
use Illuminate\Support\Collection;

/**
 * Inhoudelijke vingerafdruk van de geplande blokken op één worker×dag.
 * Wordt gebruikt om te bepalen of een save de dag echt wijzigde
 * (RosterChanged-melding) ongeacht rij-ids.
 */
class RosterDaySignature
{
    /**
     * @param  Collection<int, PlannedShift>  $rows
     */
    public static function of(Collection $rows): string
    {
        return $rows
            ->map(fn (PlannedShift $shift) => implode('|', [
                ShiftType::formatTime($shift->start_time),
                ShiftType::formatTime($shift->end_time),
                $shift->kind->value,
                (string) $shift->shift_type_id,
                (string) $shift->unit_id,
            ]))
            ->sort()
            ->implode('||');
    }
}
