<?php

declare(strict_types=1);

namespace App\Support\Units;

use InvalidArgumentException;

/**
 * Genereert unitnamen voor hotel-rasters of een opeenvolgende reeks vanaf een startnummer.
 */
final class UnitBulkNaming
{
    public const SCHEME_BLOCK_3 = 'block_3';

    public const SCHEME_COMPACT_2 = 'compact_2';

    public const SCHEME_SEQUENTIAL = 'sequential';

    public const MAX_SEQUENTIAL = 500;

    /**
     * @return list<string>
     */
    public static function schemes(): array
    {
        return [
            self::SCHEME_COMPACT_2,
            self::SCHEME_BLOCK_3,
            self::SCHEME_SEQUENTIAL,
        ];
    }

    public static function isSequential(string $scheme): bool
    {
        return $scheme === self::SCHEME_SEQUENTIAL;
    }

    /**
     * Hotel-schema's: $floorCount × $roomsPerFloor.
     * Sequential: $floorCount = startnummer, $roomsPerFloor = aantal.
     */
    public static function validateConfig(int $floorCount, int $roomsPerFloor, string $scheme): ?string
    {
        if ($scheme === self::SCHEME_SEQUENTIAL) {
            if ($floorCount < 0 || $roomsPerFloor < 1) {
                return 'invalid';
            }
            if ($roomsPerFloor > self::MAX_SEQUENTIAL) {
                return 'too_many';
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

        if ($scheme === self::SCHEME_SEQUENTIAL) {
            $names = [];
            $start = $floorCount;
            $count = $roomsPerFloor;
            for ($i = 0; $i < $count; $i++) {
                $names[] = self::withPrefix($prefix, (string) ($start + $i));
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
