<?php

namespace App\Data\Time;

use App\Enums\MonthNormStatus;

final class WorkerMonthNorm
{
    public function __construct(
        public MonthNormStatus $status,
        public ?int $minutes,
    ) {}
}
