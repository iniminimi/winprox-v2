<?php

declare(strict_types=1);

namespace App\Data\Geo;

readonly class ResolvedGeonamePlaceData
{
    public function __construct(
        public ?string $locationName,
        public ?string $countryCode,
    ) {
    }
}
