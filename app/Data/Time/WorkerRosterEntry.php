<?php

namespace App\Data\Time;

final class WorkerRosterEntry
{
    public function __construct(
        public string $date,
        public string $line,
        public string $kind,
    ) {}
}
