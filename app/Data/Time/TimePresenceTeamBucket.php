<?php

namespace App\Data\Time;

use App\Models\InternalTeam;
use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Support\Collection;

final class TimePresenceTeamBucket
{
    /**
     * @param  Collection<int, WorkShift>  $activeShifts
     * @param  Collection<int, WorkShift>  $breakShifts
     * @param  Collection<int, Worker>  $absentWorkers
     */
    public function __construct(
        public InternalTeam $team,
        public int $activeCount,
        public int $breakCount,
        public int $absentCount,
        public int $attentionCount,
        public Collection $activeShifts,
        public Collection $breakShifts,
        public Collection $absentWorkers,
    ) {}

    public function hasActivity(): bool
    {
        return $this->activeCount > 0
            || $this->breakCount > 0
            || $this->absentCount > 0
            || $this->attentionCount > 0;
    }
}
