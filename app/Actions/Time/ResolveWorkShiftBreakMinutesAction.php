<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use Carbon\CarbonInterface;

class ResolveWorkShiftBreakMinutesAction
{
    public const MIN_SHIFT_MINUTES_FOR_REQUIRED_BREAK = 240;

    public function handle(WorkShift $shift, int $recordedBreakMinutes, ?CarbonInterface $endedAt = null): int
    {
        $recorded = max(0, $recordedBreakMinutes);

        if (! $this->qualifiesForRequiredBreak($shift, $endedAt)) {
            return $recorded;
        }

        $required = (int) ($shift->team?->required_break_minutes ?? 0);

        return max($recorded, $required);
    }

    public function qualifiesForRequiredBreak(WorkShift $shift, ?CarbonInterface $endedAt = null): bool
    {
        $required = (int) ($shift->team?->required_break_minutes ?? 0);
        if ($required <= 0) {
            return false;
        }

        $clockInAt = $shift->clock_in_at;
        $end = $endedAt ?? $shift->clock_out_at;

        if ($clockInAt === null || $end === null) {
            return false;
        }

        $durationMinutes = max(0, (int) $clockInAt->diffInMinutes($end));

        return $durationMinutes >= self::MIN_SHIFT_MINUTES_FOR_REQUIRED_BREAK
            && $durationMinutes > $required;
    }
}
