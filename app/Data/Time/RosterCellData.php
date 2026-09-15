<?php

namespace App\Data\Time;

use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeKind;

final class RosterCellData
{
    public function __construct(
        public RosterCellKind $kind,
        public string $raw,
        public ?string $code = null,
        public ?int $shiftTypeId = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public int $breakMinutes = 0,
        public ?ShiftTypeKind $shiftTypeKind = null,
        public ?string $errorKey = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->kind === RosterCellKind::Empty;
    }

    public function isSavable(): bool
    {
        return $this->kind->isSavable();
    }
}
