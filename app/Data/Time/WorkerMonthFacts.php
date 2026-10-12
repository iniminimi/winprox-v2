<?php

namespace App\Data\Time;

final class WorkerMonthFacts
{
    /**
     * @param  array<string, array{days: int, minutes: int}>  $absences
     */
    public function __construct(
        public int $plannedMinutes,
        public int $workedMinutes,
        public bool $registrationIncomplete,
        public int $incompleteBlocks,
        public array $absences,
    ) {}
}
