<?php

namespace App\Data\Time;

final class TeamRosterWeekSnapshot
{
    /**
     * @param  list<TeamRosterDay>  $days
     */
    public function __construct(
        public string $weekStart,
        public string $weekEnd,
        public string $weekLabel,
        public array $days,
    ) {}
}
