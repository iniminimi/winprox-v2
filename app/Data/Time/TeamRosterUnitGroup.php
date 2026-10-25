<?php

namespace App\Data\Time;

final class TeamRosterUnitGroup
{
    /**
     * @param  list<TeamRosterEntry>  $entries
     */
    public function __construct(
        public string $label,
        public array $entries,
    ) {}
}
