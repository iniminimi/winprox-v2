<?php

namespace App\Support\Portal;

/**
 * Bewijs dat deze browser zojuist de Clock Point-QR opende (volle page-load).
 * Eén prik (in, uit of verplaatsen) per scan; een open tab / wire:poll telt niet.
 */
final class ClockPointScanGrant
{
    public const SESSION_KEY_PREFIX = 'wp_clock_point_scan_';

    public static function sessionKey(int $clockPointId): string
    {
        return self::SESSION_KEY_PREFIX.$clockPointId;
    }

    public static function ttlSeconds(): int
    {
        return max(30, (int) config('time.punch_scan_seconds', 600));
    }

    public static function grant(int $clockPointId): void
    {
        if ($clockPointId < 1) {
            return;
        }

        session([
            self::sessionKey($clockPointId) => [
                'granted_at' => now()->timestamp,
                'consumed' => false,
            ],
        ]);
    }

    public static function isValid(int $clockPointId): bool
    {
        $payload = session(self::sessionKey($clockPointId));
        if (! is_array($payload) || ($payload['consumed'] ?? true) === true) {
            return false;
        }

        $grantedAt = (int) ($payload['granted_at'] ?? 0);
        if ($grantedAt < 1) {
            return false;
        }

        return (now()->timestamp - $grantedAt) <= self::ttlSeconds();
    }

    public static function consume(int $clockPointId): void
    {
        $key = self::sessionKey($clockPointId);
        $payload = session($key);
        if (! is_array($payload)) {
            return;
        }

        $payload['consumed'] = true;
        session([$key => $payload]);
    }
}
