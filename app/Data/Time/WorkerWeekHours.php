<?php

namespace App\Data\Time;

use Carbon\Carbon;

final class WorkerWeekHours
{
    /**
     * @param  array<string, array{days: int, minutes: int}>  $absences
     */
    public function __construct(
        public ?int $contractMinutes,
        public int $plannedMinutes,
        public int $workedMinutes,
        public bool $registrationIncomplete,
        public int $incompleteBlocks,
        public array $absences,
        public int $openRegistrations = 0,
        public ?Carbon $oldestOpenClockInAt = null,
    ) {}

    public function differenceMinutes(): ?int
    {
        if ($this->contractMinutes === null) {
            return null;
        }

        return $this->plannedMinutes - $this->contractMinutes;
    }
}
