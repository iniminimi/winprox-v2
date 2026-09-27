<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;

/**
 * Heartbeat van een gekoppeld scherm: werkt display_last_seen_at bij (skips de
 * write als die net vers is — het scherm pingt ~elke minuut) en geeft de
 * verse staat terug voor de response.
 */
class RecordClockDisplayPingAction
{
    public function handle(ClockPoint $clockPoint): ClockPoint
    {
        if ($clockPoint->display_last_seen_at === null
            || $clockPoint->display_last_seen_at->lessThan(now()->subSeconds(30))) {
            $clockPoint->update(['display_last_seen_at' => now()]);
        }

        return $clockPoint->fresh();
    }
}
