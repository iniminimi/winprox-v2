<?php

namespace App\Data\Time;

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
    ) {}
}
