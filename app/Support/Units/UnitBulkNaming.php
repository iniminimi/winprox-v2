<?php

declare(strict_types=1);

namespace App\Support\Units;

use InvalidArgumentException;

/**
 * Genereert unitnamen voor een rechthoekig reeksen × units-per-reeks raster.
 */
final class UnitBulkNaming
{
    public const SCHEME_BLOCK_3 = 'block_3';

    public const SCHEME_COMPACT_2 = 'compact_2';

    public static function validateConfig(int $floorCount, int $roomsPerFloor, string $scheme): ?string
    {
        if ($floorCount < 1 || $roomsPerFloor < 1) {
            return 'invalid';
        }

        if ($scheme === self::SCHEME_COMPACT_2) {
            if ($roomsPerFloor > 9) {
                return 'scheme_rooms';
            }
            if ($floorCount > 10) {
                return 'scheme_floors';
            }

            return null;
        }

        if ($scheme === self::SCHEME_BLOCK_3) {
            if (($floorCount - 1) * 100 + $roomsPerFloor > 999) {
                return 'scheme_range';
            }

            return null;
        }

        return 'invalid';
    }

    /**
     * @return list<string>
     */
    public static function generate(int $floorCount, int $roomsPerFloor, string $scheme, string $prefix = ''): array
    {
        $error = self::validateConfig($floorCount, $roomsPerFloor, $scheme);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        $prefix = trim($prefix);
        $names = [];

        for ($f = 0; $f < $floorCount; $f++) {
            for ($r = 1; $r <= $roomsPerFloor; $r++) {
                if ($scheme === self::SCHEME_COMPACT_2) {
                    $code = str_pad((string) ($f * 10 + $r), 2, '0', STR_PAD_LEFT);
                } else {
                    $num = $f * 100 + $r;
                    $code = str_pad((string) $num, 3, '0', STR_PAD_LEFT);
                }

                if ($prefix === '') {
                    $names[] = $code;
                } else {
                    $join = preg_match('/[-_.\/\s]$/u', $prefix) === 1 ? '' : ' ';
                    $names[] = $prefix.$join.$code;
                }
            }
        }

        return $names;
    }
}
