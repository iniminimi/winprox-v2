<?php

declare(strict_types=1);

namespace App\Support\Units;

use InvalidArgumentException;

/**
 * Genereert unitnamen voor hotel-rasters of één verdieping/reeks (2 of 3 cijfers).
 *
 * Sequential: start = verdieping (bv. 2) → 20,21,22 (2 cijfers) of 201,202,203 (3 cijfers).
 */
final class UnitBulkNaming
{
    public const SCHEME_BLOCK_3 = 'block_3';

    public const SCHEME_COMPACT_2 = 'compact_2';

    public const SCHEME_SEQUENTIAL_2 = 'sequential_2';

    public const SCHEME_SEQUENTIAL_3 = 'sequential_3';

    /** @deprecated Use SCHEME_SEQUENTIAL_2 / SCHEME_SEQUENTIAL_3 */
    public const SCHEME_SEQUENTIAL = 'sequential_2';

    public const MAX_SEQUENTIAL_2 = 10;

    public const MAX_SEQUENTIAL_3 = 99;

    /**
     * @return list<string>
     */
    public static function schemes(): array
    {
        return [
            self::SCHEME_COMPACT_2,
            self::SCHEME_BLOCK_3,
            self::SCHEME_SEQUENTIAL_2,
            self::SCHEME_SEQUENTIAL_3,
        ];
    }

    public static function isSequential(string $scheme): bool
    {
        return $scheme === self::SCHEME_SEQUENTIAL_2
            || $scheme === self::SCHEME_SEQUENTIAL_3;
    }

    /**
     * Hotel-schema's: $floorCount × $roomsPerFloor.
     * Sequential: $floorCount = verdieping/reeks, $roomsPerFloor = aantal units op die reeks.
     */
    public static function validateConfig(int $floorCount, int $roomsPerFloor, string $scheme): ?string
    {
        if ($scheme === self::SCHEME_SEQUENTIAL_2) {
            if ($floorCount < 0 || $floorCount > 9 || $roomsPerFloor < 1) {
                return 'invalid';
            }
            if ($roomsPerFloor > self::MAX_SEQUENTIAL_2) {
                return 'scheme_rooms';
            }

            return null;
        }

        if ($scheme === self::SCHEME_SEQUENTIAL_3) {
            if ($floorCount < 0 || $floorCount > 9 || $roomsPerFloor < 1) {
                return 'invalid';
            }
            if ($roomsPerFloor > self::MAX_SEQUENTIAL_3) {
                return 'scheme_rooms';
            }

            return null;
        }

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

        if ($scheme === self::SCHEME_SEQUENTIAL_2) {
            $names = [];
            // Verdieping 2, aantal 3 → 20, 21, 22 (kamernummer vanaf 0).
            for ($r = 0; $r < $roomsPerFloor; $r++) {
                $code = str_pad((string) ($floorCount * 10 + $r), 2, '0', STR_PAD_LEFT);
                $names[] = self::withPrefix($prefix, $code);
            }

            return $names;
        }

        if ($scheme === self::SCHEME_SEQUENTIAL_3) {
            $names = [];
            // Verdieping 2, aantal 3 → 201, 202, 203 (kamernummer vanaf 1).
            for ($r = 1; $r <= $roomsPerFloor; $r++) {
                $code = str_pad((string) ($floorCount * 100 + $r), 3, '0', STR_PAD_LEFT);
                $names[] = self::withPrefix($prefix, $code);
            }

            return $names;
        }

        $names = [];

        for ($f = 0; $f < $floorCount; $f++) {
            for ($r = 1; $r <= $roomsPerFloor; $r++) {
                if ($scheme === self::SCHEME_COMPACT_2) {
                    $code = str_pad((string) ($f * 10 + $r), 2, '0', STR_PAD_LEFT);
                } else {
                    $num = $f * 100 + $r;
                    $code = str_pad((string) $num, 3, '0', STR_PAD_LEFT);
                }

                $names[] = self::withPrefix($prefix, $code);
            }
        }

        return $names;
    }

    private static function withPrefix(string $prefix, string $code): string
    {
        if ($prefix === '') {
            return $code;
        }

        $join = preg_match('/[-_.\/\s]$/u', $prefix) === 1 ? '' : ' ';

        return $prefix.$join.$code;
    }
}
