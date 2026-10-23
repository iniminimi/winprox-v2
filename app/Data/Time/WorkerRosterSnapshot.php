<?php

namespace App\Data\Time;

final class WorkerRosterSnapshot
{
    /**
     * @param  list<WorkerRosterEntry>  $entries
     */
    public function __construct(
        public string $monthStart,
        public string $monthEnd,
        public string $monthLabel,
        public array $entries,
    ) {}
}
