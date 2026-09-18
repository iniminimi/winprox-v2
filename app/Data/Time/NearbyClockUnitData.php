<?php

namespace App\Data\Time;

final readonly class NearbyClockUnitData
{
    public function __construct(
        public ?int $unitId,
        public int $locationId,
        public string $unitName,
        public string $locationName,
        public int $distanceMeters,
    ) {}

    /**
     * @return array{unit_id: ?int, location_id: int, unit_name: string, location_name: string, distance_meters: int}
     */
    public function toArray(): array
    {
        return [
            'unit_id' => $this->unitId,
            'location_id' => $this->locationId,
            'unit_name' => $this->unitName,
            'location_name' => $this->locationName,
            'distance_meters' => $this->distanceMeters,
        ];
    }
}
