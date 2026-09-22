<?php

namespace App\Data\Time;

final class WorkerHoursDay
{
    public function __construct(
        public string $dateKey,
        public string $title,
        public string $timesLine,
        public string $breakLine,
    ) {}
}
