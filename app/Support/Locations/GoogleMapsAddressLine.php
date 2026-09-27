<?php

namespace App\Support\Locations;

/**
 * Google Maps copies addresses as "Marktstraat 61, 8301 Knokke-Heist"
 * (optionally with a country suffix ", België"). Parsed here into the
 * location form fields: street, house_number, postal_code, city.
 */
final class GoogleMapsAddressLine
{
    private const STREET_HOUSE_PATTERN = '/^(.+?)\s+(\d+.*)$/u';

    private const POSTAL_CITY_PATTERN = '/^(\d{4})\s*(?:([A-Za-z]{2})\s+)?(.+)$/u';

    /**
     * @return array{street: string, house_number: string, postal_code: string, city: string}|null
     */
    public static function tryParse(?string $text): ?array
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if ($text === '') {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $text)),
            fn (string $part) => $part !== '',
        ));

        if (count($parts) < 2) {
            return null;
        }

        $streetPart = $parts[0];
        $cityPart = null;
        foreach (array_slice($parts, 1) as $part) {
            if (preg_match(self::POSTAL_CITY_PATTERN, $part)) {
                $cityPart = $part;
                break;
            }
        }

        if ($cityPart === null || ! preg_match(self::POSTAL_CITY_PATTERN, $cityPart, $cityMatch)) {
            return null;
        }

        $street = $streetPart;
        $houseNumber = '';
        if (preg_match(self::STREET_HOUSE_PATTERN, $streetPart, $streetMatch)) {
            $street = trim($streetMatch[1]);
            $houseNumber = trim($streetMatch[2]);
        }

        if ($street === '') {
            return null;
        }

        $postalCode = trim($cityMatch[1].($cityMatch[2] !== '' ? ' '.$cityMatch[2] : ''));
        $city = trim($cityMatch[3]);

        if ($city === '') {
            return null;
        }

        return [
            'street' => $street,
            'house_number' => $houseNumber,
            'postal_code' => $postalCode,
            'city' => $city,
        ];
    }
}
