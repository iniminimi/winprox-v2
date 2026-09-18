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
        public bool $locationCanStart = false,
        public string $locationMapsUrl = '',
        public ?float $locationPinLatitude = null,
        public ?float $locationPinLongitude = null,
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
     *     radius_meters: int,
     *     location_can_start: bool,
     *     location_maps_url: string,
     *     location_pin_latitude: ?float,
     *     location_pin_longitude: ?float
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
            'location_can_start' => $this->locationCanStart,
            'location_maps_url' => $this->locationMapsUrl,
            'location_pin_latitude' => $this->locationPinLatitude,
            'location_pin_longitude' => $this->locationPinLongitude,
        ];
    }
}
