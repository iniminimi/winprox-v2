<?php

namespace App\Data\Time;

final readonly class WorkDestinationData
{
    public function __construct(
        public string $key,
        public ?int $unitId,
        public int $locationId,
        public string $locationName,
        public ?string $unitName,
        public string $addressLine,
        public string $mapsUrl,
        public bool $canStartVisit,
        public ?float $pinLatitude,
        public ?float $pinLongitude,
        public int $radiusMeters,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     unit_id: ?int,
     *     location_id: int,
     *     location_name: string,
     *     unit_name: ?string,
     *     address_line: string,
     *     maps_url: string,
     *     can_start: bool,
     *     pin_latitude: ?float,
     *     pin_longitude: ?float,
     *     radius_meters: int
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'unit_id' => $this->unitId,
            'location_id' => $this->locationId,
            'location_name' => $this->locationName,
            'unit_name' => $this->unitName,
            'address_line' => $this->addressLine,
            'maps_url' => $this->mapsUrl,
            'can_start' => $this->canStartVisit,
            'pin_latitude' => $this->pinLatitude,
            'pin_longitude' => $this->pinLongitude,
            'radius_meters' => $this->radiusMeters,
        ];
    }
}
