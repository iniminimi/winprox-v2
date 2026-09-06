<?php

namespace App\Support\Time;

use App\Data\Time\TimePresenceAttentionItem;
use App\Enums\TimePresenceAttentionType;
use App\Models\WorkShift;
use Illuminate\Support\Collection;

final class TimePresenceAttentionRules
{
    /**
     * @param  Collection<int, WorkShift>  $openShifts
     * @return Collection<int, TimePresenceAttentionItem>
     */
    public static function collect(Collection $openShifts): Collection
    {
        $staleHours = max(1, (int) config('time.stale_shift_hours', 16));
        $longHours = max(1, (int) config('time.long_shift_hours', 10));
        $breakReminderHours = max(1, (int) config('time.break_reminder_hours', 6));

        $rapidHopMinutes = max(1, (int) config('time.rapid_hop_minutes', 5));

        $items = collect();

        foreach ($openShifts as $shift) {
            if (self::hasRapidHop($shift, $rapidHopMinutes)) {
                $items->push(new TimePresenceAttentionItem(TimePresenceAttentionType::RapidHop, $shift));

                continue;
            }

            $openHours = $shift->clock_in_at->diffInMinutes(now()) / 60;

            if ($openHours >= $staleHours) {
                $items->push(new TimePresenceAttentionItem(TimePresenceAttentionType::StaleShift, $shift));

                continue;
            }

            if ($openHours >= $longHours) {
                $items->push(new TimePresenceAttentionItem(TimePresenceAttentionType::LongShift, $shift));

                continue;
            }

            if (
                $openHours >= $breakReminderHours
                && (int) $shift->total_break_minutes === 0
                && ! $shift->isOnBreak()
                && ($shift->relationLoaded('breaks') ? $shift->breaks->isEmpty() : ! $shift->breaks()->exists())
            ) {
                $items->push(new TimePresenceAttentionItem(TimePresenceAttentionType::NoBreak, $shift));
            }
        }

        return $items
            ->sortBy(fn (TimePresenceAttentionItem $item) => $item->shift->clock_in_at)
            ->values();
    }

    private static function hasRapidHop(WorkShift $shift, int $maxMinutes): bool
    {
        $hops = $shift->locationHops();
        if ($hops === []) {
            return false;
        }

        $previous = $shift->clock_in_at;
        foreach ($hops as $hop) {
            if (! is_array($hop) || empty($hop['at']) || $previous === null) {
                continue;
            }

            try {
                $at = \Carbon\Carbon::parse((string) $hop['at']);
            } catch (\Throwable) {
                continue;
            }

            if (abs((int) $previous->diffInMinutes($at)) < $maxMinutes) {
                return true;
            }

            $previous = $at;
        }

        return false;
    }
}
