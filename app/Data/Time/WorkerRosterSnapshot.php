<?php

namespace App\Data\Time;

final class WorkerRosterSnapshot
{
    /**
     * @param  list<string>  $dates
     * @param  list<WorkerRosterEntry>  $entries
     */
    public function __construct(
        public string $weekStart,
        public string $weekEnd,
        public array $dates,
        public array $entries,
    ) {}
}
