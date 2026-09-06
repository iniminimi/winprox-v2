<?php

namespace App\Data\Time;

use Illuminate\Support\Collection;

final class TimeRosterSnapshot
{
    /**
     * @param  Collection<int, TimeRosterPerson>  $people
     * @param  Collection<string, Collection<int, TimeRosterPerson>>  $byLocation
     */
    public function __construct(
        public Collection $people,
        public Collection $byLocation,
        public int $count,
    ) {}
}
