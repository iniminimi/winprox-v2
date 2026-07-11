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
}
