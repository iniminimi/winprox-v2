<?php

namespace App\Data\Units;

readonly class ImportUnitsData
{
    public function __construct(
        public string $filePath,
        public string $originalName,
        public int $locationId,
    ) {}
}
