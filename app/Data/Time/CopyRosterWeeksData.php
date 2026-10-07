<?php

namespace App\Data\Time;

final class CopyRosterWeeksData
{
    /**
     * @param  list<int>  $workerIds
     */
    public function __construct(
        public string $sourceWeekStart,
        public string $targetWeekStart,
        public int $weekCount,
        public array $workerIds,
    ) {}
}
