<?php

namespace App\Data\Time;

use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;

final class SaveShiftTypeData
{
    public function __construct(
        public string $code,
        public string $label,
        public ?string $startTime,
        public ?string $endTime,
        public int $breakMinutes,
        public ShiftTypeColor $color,
        public bool $isActive = true,
        public ShiftTypeKind $kind = ShiftTypeKind::Work,
    ) {}
}
