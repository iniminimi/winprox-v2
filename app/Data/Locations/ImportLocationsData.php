<?php

namespace App\Data\Locations;

readonly class ImportLocationsData
{
    public function __construct(
        public string $filePath,
        public string $originalName,
    ) {}
}
