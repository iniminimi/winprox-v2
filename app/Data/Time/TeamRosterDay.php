<?php

namespace App\Data\Time;

final class TeamRosterDay
{
    /**
     * @param  list<TeamRosterUnitGroup>  $groups
     */
    public function __construct(
        public string $date,
        public string $dayLabel,
        public bool $isToday,
        public array $groups,
    ) {}
}
