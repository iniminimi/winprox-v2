<?php

namespace App\Data\Time;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class WorkerHoursSnapshot
{
    /**
     * @param  Collection<int, \App\Models\WorkShift>  $shifts
     */
    public function __construct(
        public Collection $shifts,
        public int $totalNetMinutes,
        public Carbon $from,
        public Carbon $to,
    ) {}
}
