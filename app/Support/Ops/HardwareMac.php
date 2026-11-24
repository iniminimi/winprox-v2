<?php

declare(strict_types=1);

namespace App\Support\Ops;

/**
 * MAC / device_hint: opslag = 12 hex zonder scheidingstekens;
 * weergave = AA:BB:CC:DD:EE:FF.
 */
final class HardwareMac
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $value) ?? '');

        if ($raw === '') {
            return null;
        }

        return $raw;
    }

    public static function isValid(?string $normalized): bool
    {
        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/^[0-9A-F]{12}$/', $normalized);
    }

    /** Leesbare weergave; ongeldige/korte waarden blijven ongewijzigd. */
    public static function formatForDisplay(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return $value !== null && trim($value) !== '' ? trim($value) : null;
        }

        if (! self::isValid($normalized)) {
            return $normalized;
        }

        return implode(':', str_split($normalized, 2));
    }
}
