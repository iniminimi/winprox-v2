<?php

declare(strict_types=1);

namespace App\Data\Esg;

final readonly class ExportEsgMeasurementsFilterData
{
    public function __construct(
        public ?int $indicatorId = null,
        public ?int $locationId = null,
        public ?int $unitId = null,
        public string $recordedFrom = '',
        public string $recordedTo = '',
        public bool $alarmsOnly = false,
    ) {}
}
