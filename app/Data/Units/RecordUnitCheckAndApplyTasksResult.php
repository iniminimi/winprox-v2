<?php

declare(strict_types=1);

namespace App\Data\Units;

use App\Models\UnitCheck;

final readonly class RecordUnitCheckAndApplyTasksResult
{
    public function __construct(
        public UnitCheck $check,
        public bool $appliedToInspectionRound,
        public ?string $suggestedReportDescription = null,
    ) {}
}
