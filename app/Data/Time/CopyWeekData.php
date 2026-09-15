<?php

namespace App\Data\Time;

final class CopyWeekData
{
    /**
     * @param  list<int>  $workerIds
     */
    public function __construct(
        public string $sourceWeekStart,
        public string $targetWeekStart,
        public array $workerIds,
    ) {}
}
