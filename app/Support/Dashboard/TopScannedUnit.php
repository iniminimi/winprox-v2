<?php

namespace App\Support\Dashboard;

final readonly class TopScannedUnit
{
    public function __construct(
        public int $unitId,
        public string $unitName,
        public string $locationName,
        public int $scanCount,
        public string $detailUrl,
        public string $issuesUrl,
    ) {}
}
