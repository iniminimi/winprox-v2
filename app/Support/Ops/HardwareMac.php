<?php

declare(strict_types=1);

namespace App\Support\Ops;

/**
 * Normaliseert MAC / device_hint naar 12 hex-tekens zonder scheidingstekens.
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
}
