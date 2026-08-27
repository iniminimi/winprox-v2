<?php

declare(strict_types=1);

namespace App\Data\Issues;

final readonly class ExportIssuesFilterData
{
    public function __construct(
        public string $status = '',
        public ?int $teamId = null,
        public string $search = '',
        public bool $recurringOnly = false,
        public bool $inspectionRoundOnly = false,
        public ?int $unitId = null,
    ) {}
}
