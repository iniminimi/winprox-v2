<?php

declare(strict_types=1);

namespace App\Data\UnitMeasurements;

final readonly class ExportUnitMeasurementsFilterData
{
    public function __construct(
        public ?int $locationId = null,
        public ?int $fieldId = null,
        public string $search = '',
    ) {}
}
