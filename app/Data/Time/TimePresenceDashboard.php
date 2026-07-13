<?php

namespace App\Data\Time;

use App\Models\WorkShift;
use Illuminate\Support\Collection;

final class TimePresenceDashboard
{
    /**
     * @param  Collection<int, TimePresenceAttentionItem>  $attentionItems
     * @param  Collection<int, TimePresenceTeamBucket>  $teamBuckets
     * @param  Collection<int, TimePresenceLocationBucket>  $locationBuckets
     * @param  Collection<int, WorkShift>  $searchShifts
     * @param  Collection<int, \App\Models\Worker>  $searchAbsentWorkers
     */
    public function __construct(
        public TimePresenceKpis $kpis,
        public Collection $attentionItems,
        public Collection $teamBuckets,
        public Collection $locationBuckets,
        public Collection $searchShifts,
        public Collection $searchAbsentWorkers,
        public bool $isSearchMode,
    ) {}
}
