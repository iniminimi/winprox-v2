<?php

namespace App\Data\Time;

final class WorkerRosterEntry
{
    public function __construct(
        public string $date,
        public string $dayLabel,
        public string $detail,
        public string $kind,
        public bool $weekStart = false,
    ) {}
}
