<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Admin bevestigt een pending-claim → het punt krijgt (of behoudt) zijn
 * stabiele display_id, een nieuwe display_secret (HMAC voor de roterende QR)
 * en een nieuwe wpclk_-device-token. De plaintext-token staat encrypted op
 * de claim (`issued_token`) zodat het device hem via claim-status ophaalt.
 *
 * Re-pair: een al gekoppeld punt wordt expliciet vervangen — eerst
 * display_unlinked (replaced_by_claim), dan display_claim_confirmed. Het oude
 * toestel sterft op secret én device-token en valt terug naar pairing-modus.
 */
class ConfirmClockDisplayClaimAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockDisplayClaim $claim, int $tenantId, ?int $actorUserId): ClockDisplayClaim
    {
        if ((int) $claim->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($claim, $tenantId, $actorUserId) {
            // Lock-volgorde clock_point → claims (zie SubmitClockDisplayClaimAction).
            $lockedPoint = ClockPoint::withoutGlobalScope('tenant')
                ->whereKey($claim->clock_point_id)
                ->lockForUpdate()
                ->first();

            if ($lockedPoint === null || (int) $lockedPoint->tenant_id !== $tenantId) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $locked = ClockDisplayClaim::query()
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->clock_point_id !== $lockedPoint->id) {
                throw new InvalidArgumentException('claim_not_found');
            }

            if (! $locked->isPending()) {
                throw new InvalidArgumentException('claim_not_pending');
            }

            $wasLinked = $lockedPoint->hasLinkedDisplay();
            if ($wasLinked) {
                $this->audit->record(
                    userId: $actorUserId,
                    tenantId: $tenantId,
                    action: 'clock_point.display_unlinked',
                    modelType: ClockPoint::class,
                    modelId: $lockedPoint->id,
                    payload: [
                        'clock_point_id' => $lockedPoint->id,
                        'previous_device_hint' => $lockedPoint->display_device_hint,
                        'reason' => 'replaced_by_claim',
                    ],
                );
            }

            $displayId = $lockedPoint->display_id ?? $this->generateDisplayId();
            $plainToken = 'wpclk_'.Str::lower(Str::random(40));

            $lockedPoint->update([
                'display_id' => $displayId,
                'display_secret' => bin2hex(random_bytes(32)),
                'display_token_hash' => hash('sha256', $plainToken),
                'display_token_prefix' => substr($plainToken, 0, 12),
                'display_device_hint' => $locked->device_hint,
                'display_paired_at' => now(),
                // Claim-confirm telt als eerste sync (device zet last_sync_at in NVS).
                'display_last_seen_at' => now(),
                'display_pairing_code' => null,
                'display_pairing_expires_at' => null,
            ]);

            $locked->update([
                'status' => ClockDisplayClaimStatus::Confirmed->value,
                'issued_token' => $plainToken,
                'confirmed_at' => now(),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_claim_confirmed',
                modelType: ClockDisplayClaim::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $lockedPoint->id,
                    'claim_id' => $locked->id,
                    'device_hint' => $locked->device_hint,
                    'ip' => $locked->ip,
                    'replaced_linked_display' => $wasLinked,
                ],
            );

            return $locked;
        });
    }

    private function generateDisplayId(): string
    {
        do {
            $displayId = Str::lower(Str::random(24));
        } while (ClockPoint::withoutGlobalScope('tenant')->where('display_id', $displayId)->exists());

        return $displayId;
    }
}
