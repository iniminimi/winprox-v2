<?php

namespace App\Data\Time;

final class TimePresenceKpis
{
    public function __construct(
        public int $clockedIn,
        public int $active,
        public int $onBreak,
        public int $notClockedIn,
        public int $attention,
    ) {}
}
