<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimDenyReason;
use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admin geeft een eenmalige pairing-code (XXXX-XXXX, Crockford base32) af voor
 * een Clock Point. Max. één actieve code per punt — een nieuwe code maakt de
 * vorige ongeldig én doodt in dezelfde transactie alle open pending-claims
 * (denied/code_reissued) zodat geen stale admin-tab een verlopen claim kan
 * bevestigen. Een al gekoppeld scherm blijft bewust werken tot ConfirmClaim
 * het feitelijk vervangt.
 */
class IssueClockDisplayPairingCodeAction
{
    /** Crockford base32 — geen I/L/O/U (intikbaar op een touch-keypad). */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockPoint $clockPoint, int $tenantId, ?int $actorUserId): string
    {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            ClockDisplayClaim::query()
                ->where('clock_point_id', $locked->id)
                ->activePending()
                ->lockForUpdate()
                ->get()
                ->each(function (ClockDisplayClaim $claim) use ($tenantId, $actorUserId): void {
                    $claim->update([
                        'status' => ClockDisplayClaimStatus::Denied->value,
                        'denied_reason' => ClockDisplayClaimDenyReason::CodeReissued->value,
                    ]);

                    $this->audit->record(
                        userId: $actorUserId,
                        tenantId: $tenantId,
                        action: 'clock_point.display_claim_denied',
                        modelType: ClockDisplayClaim::class,
                        modelId: $claim->id,
                        payload: [
                            'clock_point_id' => $claim->clock_point_id,
                            'claim_id' => $claim->id,
                            'device_hint' => $claim->device_hint,
                            'reason' => ClockDisplayClaimDenyReason::CodeReissued->value,
                        ],
                    );
                });

            $ttlMinutes = max(1, (int) config('time.display_pairing_ttl_minutes', 10));
            $code = $this->generateCode();

            $locked->update([
                'display_pairing_code' => $code,
                'display_pairing_expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_pairing_issued',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'replaces_linked_display' => $locked->hasLinkedDisplay(),
                    'expires_at' => $locked->display_pairing_expires_at->toIso8601String(),
                ],
            );

            return $code;
        });
    }

    private function generateCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
