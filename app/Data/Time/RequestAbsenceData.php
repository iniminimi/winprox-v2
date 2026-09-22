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
    ) {}
}
