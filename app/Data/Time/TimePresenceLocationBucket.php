<?php

namespace App\Data\Time;

use App\Models\Location;

final class TimePresenceLocationBucket
{
    public function __construct(
        public ?Location $location,
        public int $activeCount,
        public int $breakCount,
        public int $attentionCount,
        public int $clockedInCount,
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
