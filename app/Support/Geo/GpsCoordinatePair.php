<?php

namespace App\Support\Geo;

/**
 * Google Maps copies pins as "lat, lng" decimal degrees.
 */
final class GpsCoordinatePair
{
    private const PATTERN = '/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/';

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function tryParse(?string $text): ?array
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if ($text === '' || ! preg_match(self::PATTERN, $text, $matches)) {
            return null;
        }

        $lat = (float) $matches[1];
        $lng = (float) $matches[2];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    public static function format(?string $latitude, ?string $longitude): string
    {
        $lat = trim((string) $latitude);
        $lng = trim((string) $longitude);
        if ($lat === '' || $lng === '') {
            return '';
        }

        return $lat.', '.$lng;
    }
}
