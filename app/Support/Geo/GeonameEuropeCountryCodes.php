<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * GeoNames ISO-3166 country codes for Europe (continent EU).
 *
 * @see https://www.geonames.org/export/
 */
final class GeonameEuropeCountryCodes
{
    /** @var list<string> */
    private const EUROPE = [
        'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO',
        'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC',
        'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI', 'SK', 'SM', 'UA', 'VA', 'XK',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return self::EUROPE;
    }

    public static function allows(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EUROPE, true);
    }
}
