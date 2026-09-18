<?php

namespace App\Actions\Time;

use App\Data\Time\NearbyClockUnitData;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Geo\DistanceMeters;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class SuggestNearbyClockUnitsAction
{
    /**
     * @return list<NearbyClockUnitData>
     */
    public function handle(Worker $worker, float $latitude, float $longitude): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        $tenant = Tenant::query()->find((int) $worker->tenant_id);
        if ($tenant === null || ! $tenant->allowsGpsWorkVisits()) {
            throw new InvalidArgumentException('gps_visits_disabled');
        }

        $radius = $tenant->gpsVisitRadiusMeters();
        $latDelta = $radius / 111000.0;
        $lngDelta = $radius / (111000.0 * max(cos(deg2rad($latitude)), 0.01));

        $worker->loadMissing(['team', 'locations']);

        $matches = [];

        $units = Unit::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->with('location')
            ->get();

        foreach ($units as $unit) {
            $locationId = $unit->location_id !== null ? (int) $unit->location_id : null;
            if (! $worker->canClockAt($locationId) || $locationId === null) {
                continue;
            }

            $meters = DistanceMeters::between(
                $latitude,
                $longitude,
                (float) $unit->latitude,
                (float) $unit->longitude,
            );
            if ($meters > $radius) {
                continue;
            }

            $matches[] = new NearbyClockUnitData(
                (int) $unit->id,
                $locationId,
                (string) $unit->name,
                (string) ($unit->location?->name ?? ''),
                (int) round($meters),
            );
        }

        $locations = Location::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->get();

        $unitLocationIds = [];
        foreach ($matches as $match) {
            if ($match->unitId !== null) {
                $unitLocationIds[$match->locationId] = true;
            }
        }

        foreach ($locations as $location) {
            $locationId = (int) $location->id;
            if (! $worker->canClockAt($locationId)) {
                continue;
            }
            if (isset($unitLocationIds[$locationId])) {
                continue;
            }

            $meters = DistanceMeters::between(
                $latitude,
                $longitude,
                (float) $location->latitude,
                (float) $location->longitude,
            );
            if ($meters > $radius) {
                continue;
            }

            $matches[] = new NearbyClockUnitData(
                null,
                $locationId,
                '',
                (string) ($location->name ?: $location->formattedAddress()),
                (int) round($meters),
            );
        }

        usort($matches, fn (NearbyClockUnitData $a, NearbyClockUnitData $b) => $a->distanceMeters <=> $b->distanceMeters);

        return $matches;
    }
}
