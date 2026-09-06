<?php

namespace App\Enums;

enum TimePresenceAttentionType: string
{
    case LongShift = 'long_shift';
    case StaleShift = 'stale_shift';
    case NoBreak = 'no_break';
    case RapidHop = 'rapid_hop';
    case RosterViewed = 'roster_viewed';

    public function thresholdValue(): int
    {
        return match ($this) {
            self::StaleShift => max(1, (int) config('time.stale_shift_hours', 16)),
            self::LongShift => max(1, (int) config('time.long_shift_hours', 10)),
            self::NoBreak => max(1, (int) config('time.break_reminder_hours', 6)),
            self::RapidHop => max(1, (int) config('time.rapid_hop_minutes', 5)),
            self::RosterViewed => 0,
        };
    }
}
