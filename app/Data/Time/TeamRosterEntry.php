<?php

namespace App\Data\Time;

final class TeamRosterEntry
{
    public function __construct(
        public string $workerName,
        public bool $isViewer,
        public string $duty,
        public string $hours,
        public string $kind,
    ) {}
}
