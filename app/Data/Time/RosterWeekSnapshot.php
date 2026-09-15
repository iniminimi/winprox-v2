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
     * @param  list<int>  $dayNumbers
     * @param  array<string, string>  $attendance
     * @param  array<string, string>  $attendanceMessages
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
        public string $period = 'week',
        public string $monthLabel = '',
        public array $dayNumbers = [],
        public array $attendance = [],
        public array $attendanceMessages = [],
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
            'period' => $this->period,
            'month_label' => $this->monthLabel,
            'day_numbers' => $this->dayNumbers,
            'attendance' => $this->attendance,
            'attendance_messages' => $this->attendanceMessages,
        ];
    }
}
