<?php

declare(strict_types=1);

namespace App\Data\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;

readonly class SaveUnitMeasureFieldData
{
    /**
     * @param  list<string>|null  $options
     */
    public function __construct(
        public string $name,
        public UnitMeasureFieldType $type,
        public ?string $unitOfMeasure = null,
        public ?float $minValue = null,
        public ?float $maxValue = null,
        public ?array $options = null,
        public bool $isActive = true,
    ) {}
}
