<?php

declare(strict_types=1);

namespace App\Data\Sense;

final readonly class SenseReadingCatalog
{
    /**
     * @param  list<array{id: int, name: string}>  $locations
     * @param  list<SenseReadingScreenChoice>  $screens
     */
    public function __construct(
        public array $locations,
        public array $screens,
    ) {}

    public function find(int $sensePointId): ?SenseReadingScreenChoice
    {
        foreach ($this->screens as $screen) {
            if ($screen->sensePointId === $sensePointId) {
                return $screen;
            }
        }

        return null;
    }

    public function firstInLocation(int $locationId): ?SenseReadingScreenChoice
    {
        foreach ($this->screens as $screen) {
            if ($screen->locationId === $locationId) {
                return $screen;
            }
        }

        return null;
    }
}
