<?php

namespace App\Data\Time;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class WorkerHoursSnapshot
{
    /**
     * @param  Collection<int, \App\Models\WorkShift>  $shifts
     * @param  Collection<int, WorkerHoursDay>  $days
     */
    public function __construct(
        public Collection $shifts,
        public Collection $days,
        public int $totalNetMinutes,
        public Carbon $from,
        public Carbon $to,
    ) {}
}
