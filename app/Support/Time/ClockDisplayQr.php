<?php

namespace App\Support\Time;

use App\Models\ClockPoint;

/**
 * Dynamische QR-token voor klokschermen (ESP32-4848S040).
 *
 * Layout: {display_id:24}{dyn:40} — 64 tekens, past in dezelfde portal-route
 * als de statische 40-teken QR. `dyn` = eerste 40 hex van
 * HMAC-SHA256(display_secret, "wpx:<unix_venster>") — het scherm en de server
 * rekenen dit onafhankelijk uit; er is dus geen DB-rij per rotatie nodig.
 * Firmware: ESP32-S3 spiegelt dit met mbedtls HMAC op dezelfde venstermath.
 */
final class ClockDisplayQr
{
    public const DISPLAY_ID_LENGTH = 24;

    public const DYN_LENGTH = 40;

    public const TOKEN_LENGTH = self::DISPLAY_ID_LENGTH + self::DYN_LENGTH;

    public static function windowSeconds(): int
    {
        return (int) config('time.display_window_seconds', 30);
    }

    /** Aantal vensters voor/na die nog geldig zijn (klokdrift-tolerantie). */
    public static function toleranceWindows(): int
    {
        return (int) config('time.display_window_tolerance', 1);
    }

    /** Volledige portal-token voor venster $window (zoals het scherm hem toont). */
    public static function tokenFor(ClockPoint $clockPoint, int $window): ?string
    {
        if (! $clockPoint->hasLinkedDisplay()) {
            return null;
        }

        return $clockPoint->display_id.self::dynFor($clockPoint->display_secret, $window);
    }

    public static function dynFor(string $secret, int $window): string
    {
        return substr(hash_hmac('sha256', 'wpx:'.$window, $secret), 0, self::DYN_LENGTH);
    }

    public static function currentWindow(?int $unixTs = null): int
    {
        return intdiv($unixTs ?? time(), self::windowSeconds());
    }

    /**
     * Is {display_id}{dyn} een geldige token voor dit punt? Probeer het huidige
     * venster ± tolerance met constante-tijd-vergelijking.
     */
    public static function matches(ClockPoint $clockPoint, string $dyn, ?int $unixTs = null): bool
    {
        if (! $clockPoint->hasLinkedDisplay() || strlen($dyn) !== self::DYN_LENGTH) {
            return false;
        }

        $window = self::currentWindow($unixTs);
        $tolerance = self::toleranceWindows();
        $secret = $clockPoint->display_secret;

        for ($offset = -$tolerance; $offset <= $tolerance; $offset++) {
            if (hash_equals(self::dynFor($secret, $window + $offset), $dyn)) {
                return true;
            }
        }

        return false;
    }
}
