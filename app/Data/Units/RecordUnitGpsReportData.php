<?php

namespace App\Data\Units;

use Carbon\CarbonImmutable;

readonly class RecordUnitGpsReportData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public CarbonImmutable $reportedAt,
    ) {
    }

    /** @param array{latitude: float|int|string, longitude: float|int|string, reported_at: string} $input */
    public static function fromValidated(array $input): self
    {
        return new self(
            latitude: (float) $input['latitude'],
            longitude: (float) $input['longitude'],
            reportedAt: CarbonImmutable::parse($input['reported_at']),
        );
    }
}
