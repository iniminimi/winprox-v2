<?php

namespace App\Support\Portal;

/**
 * Bewijs dat deze browser het portaal opende met een toen geldige token.
 * Roterende scherm-QR's (dynamische Clock Point-tokens) verlopen al na ~30s
 * terwijl de pagina openstaat — zonder deze marker zou elke wire:poll of
 * Livewire-call daarna op de "QR niet geldig"-kaart uitkomen. De marker
 * houdt alleen een al geopende sessie levend; een verlopen token opent
 * nooit een nieuwe sessie (mount blokkeert die nog steeds).
 */
final class PortalEntryToken
{
    private const SESSION_KEY_PREFIX = 'wp_time_portal_entry_';

    private const TTL_SECONDS = 43200;

    private static function sessionKey(string $token): string
    {
        return self::SESSION_KEY_PREFIX.$token;
    }

    public static function grant(string $token, int $clockPointId): void
    {
        if ($token === '' || $clockPointId < 1) {
            return;
        }

        session([
            self::sessionKey($token) => [
                'clock_point_id' => $clockPointId,
                'granted_at' => now()->timestamp,
            ],
        ]);
    }

    public static function clockPointId(string $token): ?int
    {
        $payload = session(self::sessionKey($token));
        if (! is_array($payload)) {
            return null;
        }

        $grantedAt = (int) ($payload['granted_at'] ?? 0);
        if ($grantedAt < 1 || (now()->timestamp - $grantedAt) > self::TTL_SECONDS) {
            return null;
        }

        $id = (int) ($payload['clock_point_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
