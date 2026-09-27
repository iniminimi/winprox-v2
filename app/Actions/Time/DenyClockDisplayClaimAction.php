<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimDenyReason;
use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DenyClockDisplayClaimAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockDisplayClaim $claim, int $tenantId, ?int $actorUserId): ClockDisplayClaim
    {
        if ((int) $claim->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($claim, $tenantId, $actorUserId) {
            // Lock-volgorde clock_point → claims (zie SubmitClockDisplayClaimAction).
            ClockPoint::withoutGlobalScope('tenant')
                ->whereKey($claim->clock_point_id)
                ->lockForUpdate()
                ->first();

            $locked = ClockDisplayClaim::query()
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isPending()) {
                throw new InvalidArgumentException('claim_not_pending');
            }

            $locked->update([
                'status' => ClockDisplayClaimStatus::Denied->value,
                'denied_reason' => ClockDisplayClaimDenyReason::Admin->value,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_claim_denied',
                modelType: ClockDisplayClaim::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->clock_point_id,
                    'claim_id' => $locked->id,
                    'device_hint' => $locked->device_hint,
                    'reason' => ClockDisplayClaimDenyReason::Admin->value,
                ],
            );

            return $locked;
        });
    }
}
