<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use Carbon\CarbonInterface;

class ResolveWorkShiftBreakMinutesAction
{
    public function handle(WorkShift $shift, int $recordedBreakMinutes, ?CarbonInterface $endedAt = null): int
    {
        $recorded = max(0, $recordedBreakMinutes);
        $required = (int) ($shift->team?->required_break_minutes ?? 0);

        if ($required <= 0) {
            return $recorded;
        }

        $clockInAt = $shift->clock_in_at;
        $end = $endedAt ?? $shift->clock_out_at;

        if ($clockInAt === null || $end === null) {
            return $recorded;
        }

        $durationMinutes = max(0, (int) $clockInAt->diffInMinutes($end));
        if ($durationMinutes <= $required) {
            return $recorded;
        }

        return max($recorded, $required);
    }
}
