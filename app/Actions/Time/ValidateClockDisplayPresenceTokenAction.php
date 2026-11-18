<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayMode;
use App\Models\ClockPoint;
use App\Support\Time\ClockDisplayPresenceToken;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Valideert een BLE-aanwezigheidstoken van een klokscherm.
 * Anti-replay: cache-set per display_id:boot_id:counter (geen "laatste counter").
 */
class ValidateClockDisplayPresenceTokenAction
{
    /**
     * @return array{clock_point: ClockPoint, display_id: string, boot_id: int, counter: int}
     */
    public function handle(string $token, int $tenantId): array
    {
        $parsed = ClockDisplayPresenceToken::parse($token);
        if ($parsed === null) {
            throw new InvalidArgumentException('presence_token_invalid');
        }

        $ttl = max(10, (int) config('time.presence_token_ttl_seconds', 30));
        $drift = max(0, (int) config('time.presence_token_drift_seconds', 5));
        $now = time();
        if ($parsed['timestamp'] < $now - $ttl - $drift || $parsed['timestamp'] > $now + $drift) {
            throw new InvalidArgumentException('presence_token_expired');
        }

        $clockPoint = ClockPoint::query()
            ->where('tenant_id', $tenantId)
            ->where('display_id', $parsed['display_id'])
            ->where('is_active', true)
            ->first();

        if ($clockPoint === null || ! $clockPoint->hasLinkedDisplay()) {
            throw new InvalidArgumentException('presence_token_unknown');
        }

        $mode = $clockPoint->display_clock_mode instanceof ClockDisplayMode
            ? $clockPoint->display_clock_mode
            : ClockDisplayMode::tryFrom((string) $clockPoint->display_clock_mode);
        if ($mode !== ClockDisplayMode::Ble) {
            throw new InvalidArgumentException('presence_token_mode');
        }

        $expected = ClockDisplayPresenceToken::hmacHex(
            (string) $clockPoint->display_secret,
            $parsed['display_id'],
            $parsed['timestamp'],
            $parsed['boot_id'],
            $parsed['counter'],
        );
        if (! hash_equals($expected, $parsed['hmac'])) {
            throw new InvalidArgumentException('presence_token_invalid');
        }

        $replayKey = ClockDisplayPresenceToken::replayKey(
            $parsed['display_id'],
            $parsed['boot_id'],
            $parsed['counter'],
        );
        $replayTtl = max($ttl * 2, 60);
        if (! Cache::add($replayKey, 1, $replayTtl)) {
            throw new InvalidArgumentException('presence_token_replay');
        }

        return [
            'clock_point' => $clockPoint,
            'display_id' => $parsed['display_id'],
            'boot_id' => $parsed['boot_id'],
            'counter' => $parsed['counter'],
        ];
    }
}
