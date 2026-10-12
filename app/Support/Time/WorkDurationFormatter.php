<?php

declare(strict_types=1);

namespace App\Support\Time;

final class WorkDurationFormatter
{
    public static function format(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return $minutes.__('time.duration.minute_short');
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($remaining === 0) {
            return $hours.__('time.duration.hour_short');
        }

        return $hours.__('time.duration.hour_short').$remaining.__('time.duration.minute_short');
    }

    /** Smalle cijferkolommen (rooster): 15u32, 8u05, 0u45, 15u. */
    public static function compact(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        $hour = __('time.duration.hour_short');

        if ($remaining === 0) {
            return $hours.$hour;
        }

        return $hours.$hour.str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
    }
}
