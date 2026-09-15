<?php

namespace App\Data\Time;

final class SavePlannedShiftsData
{
    /**
     * @param  list<int>  $workerIds
     * @param  list<array{worker_id: int, date: string, raw: string}>  $cells
     */
    public function __construct(
        public string $weekStart,
        public array $workerIds,
        public array $cells,
    ) {}
}
