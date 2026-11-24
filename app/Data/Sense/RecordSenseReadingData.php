<?php

declare(strict_types=1);

namespace App\Data\Sense;

use Carbon\CarbonImmutable;

final class RecordSenseReadingData
{
    public function __construct(
        public float $temperature,
        public float $humidity,
        public int $co2,
        public int $voc,
        public CarbonImmutable $recordedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            temperature: round((float) $validated['temperature'], 1),
            humidity: round((float) $validated['humidity'], 1),
            co2: (int) $validated['co2'],
            voc: (int) $validated['voc'],
            recordedAt: CarbonImmutable::parse((string) $validated['recorded_at']),
        );
    }
}
