<?php

namespace App\Data\Time;

use App\Enums\ShiftTypeColor;

final class SaveShiftTypeData
{
    public function __construct(
        public string $code,
        public string $label,
        public string $startTime,
        public string $endTime,
        public int $breakMinutes,
        public ShiftTypeColor $color,
        public bool $isActive = true,
    ) {}
}
