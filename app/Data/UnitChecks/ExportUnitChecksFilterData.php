<?php

declare(strict_types=1);

namespace App\Data\UnitChecks;

final readonly class ExportUnitChecksFilterData
{
    public function __construct(
        public string $result = 'all',
        public ?int $locationId = null,
    ) {}
}
