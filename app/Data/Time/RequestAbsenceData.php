<?php

namespace App\Data\Time;

use App\Enums\ShiftTypeKind;
use Illuminate\Http\UploadedFile;

final class RequestAbsenceData
{
    /**
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public ShiftTypeKind $kind,
        public string $dateFrom,
        public string $dateTo,
        public ?string $description = null,
        public bool $halfDay = false,
        /** Portal: concrete ShiftType id. Without id, leave/recup/sick use first active type. */
        public ?int $shiftTypeId = null,
        public array $photos = [],
    ) {}
}
