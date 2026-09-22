<?php

namespace App\Data\Time;

use App\Enums\PortalRosterClockAlert;

final class WorkerHoursDay
{
    public function __construct(
        public string $dateKey,
        public string $dateLabel,
        public bool $isOpen,
        public string $timesLine,
        public string $breakLine,
        public ?PortalRosterClockAlert $clockAlert = null,
    ) {}
}
