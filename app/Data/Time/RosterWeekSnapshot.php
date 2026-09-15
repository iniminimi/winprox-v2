<?php

namespace App\Data\Time;

final class RosterWeekSnapshot
{
    /**
     * @param  list<string>  $dates
     * @param  list<string>  $dayLabels
     * @param  list<array<string, mixed>>  $workers
     * @param  array<string, array<string, mixed>>  $cells
     * @param  list<array<string, mixed>>  $types
     * @param  list<array<string, mixed>>  $teams
     */
    public function __construct(
        public string $weekStart,
        public string $weekEnd,
        public array $dates,
        public array $dayLabels,
        public array $workers,
        public array $cells,
        public array $types,
        public array $teams,
        public bool $weekPublished,
        public bool $nightShifts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'dates' => $this->dates,
            'day_labels' => $this->dayLabels,
            'workers' => $this->workers,
            'cells' => $this->cells,
            'types' => $this->types,
            'teams' => $this->teams,
            'week_published' => $this->weekPublished,
            'night_shifts' => $this->nightShifts,
            'free_color' => 'slate',
        ];
    }
}
