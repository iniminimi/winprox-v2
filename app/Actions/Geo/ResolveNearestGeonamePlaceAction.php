<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Data\Geo\ResolvedGeonamePlaceData;
use App\Models\GeonamePlace;

class ResolveNearestGeonamePlaceAction
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** @var list<float> */
    private const SEARCH_RADII_KM = [2.0, 5.0, 15.0, 50.0];

    /** @var list<string> */
    private const SIGNIFICANT_WATER_FEATURE_CODES = ['LK', 'BAY', 'PND', 'LKN', 'WTRC'];

    /** @var list<string> */
    private const MINOR_HYDRO_FEATURE_CODES = ['STM', 'DTCH', 'CNL', 'DRG', 'SHOL', 'FISH', 'COVE', 'INLT'];

    /** @var list<string> */
    private const LOW_VALUE_SPOT_FEATURE_CODES = ['HTL', 'BLDG', 'MALL', 'RET', 'RST'];

    public function handle(float $latitude, float $longitude): ResolvedGeonamePlaceData
    {
        if (! GeonamePlace::query()->exists()) {
            return new ResolvedGeonamePlaceData(null, null);
        }

        foreach (self::SEARCH_RADII_KM as $radiusKm) {
            $match = $this->bestMatchWithinRadius($latitude, $longitude, $radiusKm);

            if ($match !== null) {
                return new ResolvedGeonamePlaceData(
                    locationName: $this->displayLocationName($match),
                    countryCode: $match->country_code,
                );
            }
        }

        return new ResolvedGeonamePlaceData(null, null);
    }

    private function bestMatchWithinRadius(float $latitude, float $longitude, float $radiusKm): ?GeonamePlace
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($latitude)), 0.01));

        $candidates = GeonamePlace::query()
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->orderByRaw(
                '((latitude - ?) * (latitude - ?) + (longitude - ?) * (longitude - ?))',
                [$latitude, $latitude, $longitude, $longitude],
            )
            ->limit(250)
            ->get();

        $best = null;
        $bestScore = PHP_FLOAT_MAX;

        foreach ($candidates as $candidate) {
            $distanceKm = $this->haversineDistanceKm(
                $latitude,
                $longitude,
                (float) $candidate->latitude,
                (float) $candidate->longitude,
            );

            if ($distanceKm > $radiusKm) {
                continue;
            }

            $score = $this->selectionScore($candidate, $distanceKm);

            if ($score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function selectionScore(GeonamePlace $place, float $distanceKm): float
    {
        $distanceKm += $this->distancePenaltyKm($place);

        if ($this->isSignificantWater($place)) {
            return $distanceKm;
        }

        if ($place->feature_class === 'P') {
            return $distanceKm + 0.25;
        }

        if (in_array($place->feature_class, ['V', 'L'], true)) {
            return $distanceKm + 0.5;
        }

        if ($place->feature_class === 'T') {
            return $distanceKm + 0.75;
        }

        return $distanceKm + 1.0;
    }

    private function distancePenaltyKm(GeonamePlace $place): float
    {
        if ($place->feature_class === 'H' && $this->isMinorHydro($place->feature_code)) {
            return 2.0;
        }

        if ($place->feature_class === 'S' && in_array($place->feature_code, self::LOW_VALUE_SPOT_FEATURE_CODES, true)) {
            return 1.5;
        }

        return 0.0;
    }

    private function isSignificantWater(GeonamePlace $place): bool
    {
        return $place->feature_class === 'H'
            && in_array($place->feature_code, self::SIGNIFICANT_WATER_FEATURE_CODES, true);
    }

    private function isMinorHydro(string $featureCode): bool
    {
        return in_array($featureCode, self::MINOR_HYDRO_FEATURE_CODES, true);
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    private function displayLocationName(GeonamePlace $place): ?string
    {
        if ($this->isGenericOpenWater($place)) {
            return null;
        }

        return $place->name;
    }

    private function isGenericOpenWater(GeonamePlace $place): bool
    {
        if (in_array($place->feature_code, ['SEA', 'OCN'], true)) {
            return true;
        }

        $name = trim($place->name);

        return str_ends_with($name, ' Ocean')
            || in_array($name, ['North Sea', 'Norwegian Sea', 'Baltic Sea', 'Mediterranean Sea'], true);
    }
}
