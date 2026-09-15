<?php

namespace App\Data\Time;

final class PublishWeekData
{
    /**
     * @param  list<int>  $workerIds
     */
    public function __construct(
        public string $weekStart,
        public array $workerIds,
        public string $period = 'week',
    ) {}
}
