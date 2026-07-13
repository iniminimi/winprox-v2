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

        $items = collect();

        foreach ($openShifts as $shift) {
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
}
