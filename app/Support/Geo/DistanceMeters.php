<?php

namespace App\Support\Geo;

final class DistanceMeters
{
    public static function between(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($latB - $latA);
        $dLng = deg2rad($lngB - $lngA);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
