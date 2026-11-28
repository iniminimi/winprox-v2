<?php

declare(strict_types=1);

namespace App\Data\Sense;

final readonly class SenseReadingScreenChoice
{
    public function __construct(
        public int $sensePointId,
        public int $locationId,
        public string $locationName,
        public string $name,
        public ?int $unitId,
        public ?int $clockPointId,
    ) {}
}
