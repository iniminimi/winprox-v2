<?php

declare(strict_types=1);

namespace App\Support\Screens;

use App\Models\Screen;

/**
 * Dynamische QR op een scherm. Een Clock Point wisselt elke 30 seconden.
 * Een scherm dat alleen aan een lokaal hangt, wisselt elk uur.
 */
final class ScreenQr
{
    public const WINDOW_CLOCK = 30;

    public const WINDOW_UNIT = 3600;

    public static function windowSeconds(Screen $screen): int
    {
        if ($screen->clock_point_id !== null) {
            return self::WINDOW_CLOCK;
        }

        $stored = (int) ($screen->qr_window_seconds ?? 0);

        return $stored > 0 ? $stored : self::WINDOW_UNIT;
    }

    public static function currentWindow(Screen $screen, ?int $unixTs = null): int
    {
        return intdiv($unixTs ?? time(), self::windowSeconds($screen));
    }

    public static function tokenFor(Screen $screen, ?int $unixTs = null): ?string
    {
        if ($screen->display_id === null || $screen->display_secret === null || $screen->unit_id === null) {
            return null;
        }

        return $screen->display_id.self::dyn($screen->display_secret, self::currentWindow($screen, $unixTs));
    }

    public static function portalUrl(Screen $screen, ?int $unixTs = null): ?string
    {
        $token = self::tokenFor($screen, $unixTs);
        if ($token === null) {
            return null;
        }

        return url(route('public.unit-portal', ['token' => $token], absolute: false));
    }

    public static function matches(Screen $screen, string $dyn, ?int $unixTs = null): bool
    {
        if ($screen->display_secret === null || strlen($dyn) !== 16) {
            return false;
        }

        $window = self::currentWindow($screen, $unixTs);
        $expected = self::dyn($screen->display_secret, $window);

        return hash_equals($expected, $dyn);
    }

    public static function dyn(string $secret, int $window): string
    {
        return substr(hash_hmac('sha256', 'wpx:'.$window, $secret), 0, 16);
    }
}
