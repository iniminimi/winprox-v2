<?php

namespace App\Data\Time;

use App\Enums\ShiftTypeKind;

final class RequestAbsenceData
{
    public function __construct(
        public ShiftTypeKind $kind,
        public string $dateFrom,
        public string $dateTo,
        public ?string $description = null,
        public bool $halfDay = false,
        /** Required for kind=other: concrete ShiftType id. Leave/recup use first active type. */
        public ?int $shiftTypeId = null,
    ) {}
}
