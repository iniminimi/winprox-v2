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
        /** Portal: concrete ShiftType id. Without id, leave/recup/sick use first active type. */
        public ?int $shiftTypeId = null,
    ) {}
}
