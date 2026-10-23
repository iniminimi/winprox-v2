<?php

namespace App\Data\Time;

final class TeamRosterEntry
{
    /**
     * @param  list<string>  $duties
     * @param  list<string>  $hourLines
     */
    public function __construct(
        public string $workerName,
        public bool $isViewer,
        public array $duties,
        public array $hourLines,
        public string $kind,
    ) {}
}
