<?php

namespace App\Data\Time;

use App\Enums\PortalRosterClockAlert;

final class WorkerRosterEntry
{
    public function __construct(
        public string $date,
        public string $dayLabel,
        public string $duty,
        public string $hours,
        public string $kind,
        public bool $weekStart = false,
        public bool $isToday = false,
        public ?PortalRosterClockAlert $clockAlert = null,
    ) {}
}
