<?php

namespace App\Data\Time;

use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Support\Collection;

final class TimePresenceSnapshot
{
    /**
     * @param  Collection<int, WorkShift>  $present
     * @param  Collection<int, WorkShift>  $onBreak
     * @param  Collection<int, Worker>  $notClockedIn
     */
    public function __construct(
        public Collection $present,
        public Collection $onBreak,
        public Collection $notClockedIn,
    ) {}
}
