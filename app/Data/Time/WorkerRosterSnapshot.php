<?php

namespace App\Data\Time;

use Carbon\Carbon;

final class WorkerRosterSnapshot
{
    /**
     * @param  list<WorkerRosterEntry>  $entries
     */
    public function __construct(
        public string $monthStart,
        public string $monthEnd,
        public string $monthLabel,
        public array $entries,
    ) {}

    /**
     * @return array<string, list<WorkerRosterEntry>> per week, keyed op de maandag (yyyy-mm-dd)
     */
    public function weeks(): array
    {
        $weeks = [];
        foreach ($this->entries as $entry) {
            $key = Carbon::parse($entry->date)->startOfWeek()->toDateString();
            $weeks[$key][] = $entry;
        }

        return $weeks;
    }
}
