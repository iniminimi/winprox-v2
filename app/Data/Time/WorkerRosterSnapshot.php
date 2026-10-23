<?php

namespace App\Data\Time;

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
     * @return list<list<WorkerRosterEntry>>
     */
    public function weeks(): array
    {
        $weeks = [];
        foreach ($this->entries as $entry) {
            if ($entry->weekStart || $weeks === []) {
                $weeks[] = [];
            }
            $weeks[array_key_last($weeks)][] = $entry;
        }

        return $weeks;
    }
}
