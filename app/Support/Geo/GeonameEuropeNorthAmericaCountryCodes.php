<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * GeoNames ISO-3166 country codes for Europe and North America (continent EU + NA).
 *
 * @see https://www.geonames.org/export/
 */
final class GeonameEuropeNorthAmericaCountryCodes
{
    /** @var list<string> */
    private const EUROPE = [
        'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO',
        'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC',
        'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI', 'SK', 'SM', 'UA', 'VA', 'XK',
    ];

    /** @var list<string> */
    private const NORTH_AMERICA = [
        'AG', 'AI', 'AW', 'BB', 'BL', 'BM', 'BQ', 'BS', 'BZ', 'CA', 'CR', 'CU', 'CW', 'DM', 'DO', 'GD', 'GL', 'GP',
        'GT', 'HN', 'HT', 'JM', 'KN', 'KY', 'LC', 'MF', 'MQ', 'MS', 'MX', 'NI', 'PA', 'PM', 'PR', 'SV', 'SX', 'TC',
        'TT', 'US', 'VC', 'VG', 'VI',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(self::EUROPE, self::NORTH_AMERICA)));
    }

    public static function allows(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::all(), true);
    }
}
