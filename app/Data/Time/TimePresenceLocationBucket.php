<?php

namespace App\Data\Time;

use App\Models\Location;
use Illuminate\Support\Collection;

final class TimePresenceLocationBucket
{
    /**
     * @param  Collection<int, \App\Models\WorkShift>  $shifts
     */
    public function __construct(
        public ?Location $location,
        public int $activeCount,
        public int $breakCount,
        public int $attentionCount,
        public int $clockedInCount,
        public Collection $shifts,
    ) {}

    public function hasActivity(): bool
    {
        return $this->clockedInCount > 0 || $this->attentionCount > 0;
    }

    public function label(): string
    {
        return $this->location?->name ?? __('time.presence.unknown_location');
    }
}
