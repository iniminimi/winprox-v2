<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Device (ESP32-scherm) tikt de pairing-code in en claimt het Clock Point.
 * Maakt een pending-claim aan — de admin moet expliciet bevestigen vóór er
 * credentials uitgegaan. Single-pending-per-punt: structureel via de
 * pending_slot unique index; zelfde device_hint retryt idempotent.
 * Publiek endpoint — geen tenant-scope op de lookups (device is nog niet
 * gekoppeld); de code zelf + admin-confirm zijn de toegangspoort.
 */
class SubmitClockDisplayClaimAction
{
    public function handle(string $code, string $deviceHint, ?string $ip): ClockDisplayClaim
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === '') {
            throw new InvalidArgumentException('pairing_code_invalid');
        }

        $point = ClockPoint::withoutGlobalScope('tenant')
            ->where('display_pairing_code', $normalized)
            ->where('display_pairing_expires_at', '>', now())
            ->first();

        if ($point === null) {
            throw new InvalidArgumentException('pairing_code_invalid');
        }

        // Cooldown op claim-creatie per punt (bovenop route-throttle): een
        // codehouder kan single-pending niet omzeilen door te claimen → deny →
        // opnieuw claimen om de admin-lijst te vullen.
        $rateKey = 'clock-display-claims:'.$point->id;
        $maxPerHour = max(1, (int) config('time.display_claims_per_hour', 5));
        if (RateLimiter::tooManyAttempts($rateKey, $maxPerHour)) {
            throw new InvalidArgumentException('claim_rate_limited');
        }

        return DB::transaction(function () use ($point, $normalized, $deviceHint, $ip, $rateKey) {
            // Lock-volgorde: altijd eerst clock_point, dan claims (deadlock-vrij
            // tegenover IssuePairingCode/ConfirmClaim die dezelfde volgorde houden).
            $locked = ClockPoint::withoutGlobalScope('tenant')
                ->whereKey($point->id)
                ->lockForUpdate()
                ->first();

            // Race met IssuePairingCode: de code kan net heruitgegeven zijn.
            if ($locked === null
                || $locked->display_pairing_code === null
                || ! hash_equals($locked->display_pairing_code, $normalized)
                || $locked->display_pairing_expires_at === null
                || $locked->display_pairing_expires_at->isPast()) {
                throw new InvalidArgumentException('pairing_code_invalid');
            }

            $existing = ClockDisplayClaim::query()
                ->where('clock_point_id', $locked->id)
                ->activePending()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                // Zelfde device retryt (wifi-hapering) → idempotent dezelfde
                // claim terug. Ander device → conflicterende pending.
                if ($existing->device_hint === $deviceHint) {
                    return $existing;
                }

                throw new InvalidArgumentException('claim_pending');
            }

            try {
                $claim = ClockDisplayClaim::query()->create([
                    'tenant_id' => $locked->tenant_id,
                    'clock_point_id' => $locked->id,
                    'claim_token' => Str::lower(Str::random(40)),
                    'pairing_code' => $normalized,
                    'device_hint' => $deviceHint,
                    'ip' => $ip,
                    'status' => ClockDisplayClaimStatus::Pending->value,
                    'expires_at' => $locked->display_pairing_expires_at,
                ]);
            } catch (QueryException $e) {
                // pending_slot unique violation → gelijktijdige claim van een
                // ander device won de race.
                throw new InvalidArgumentException('claim_pending', previous: $e);
            }

            RateLimiter::hit($rateKey, 3600);

            return $claim;
        });
    }

    /** "7F2K-9Q3M", "7f2k 9q3m", "7f2k9q3m" → "7F2K9Q3M". */
    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}
