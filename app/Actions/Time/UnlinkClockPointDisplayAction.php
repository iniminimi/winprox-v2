<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Scherm ontkoppelen (diefstal, defect, verhuizing). display_id blijft staan —
 * het is de publieke sleutel van het punt; met lege secret/token falen oude
 * QR's (blocked + gelogd, audit-spoor behouden) en sterft de wpclk_-token.
 * Het scherm zelf krijgt 401 op ping en valt terug naar pairing-modus.
 */
class UnlinkClockPointDisplayAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockPoint $clockPoint, int $tenantId, ?int $actorUserId): ClockPoint
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

            if (! $locked->hasLinkedDisplay()) {
                throw new InvalidArgumentException('display_not_linked');
            }

            $locked->update([
                'display_secret' => null,
                'display_token_hash' => null,
                'display_token_prefix' => null,
                'display_pairing_code' => null,
                'display_pairing_expires_at' => null,
                'display_device_hint' => null,
                'display_paired_at' => null,
                'display_last_seen_at' => null,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_unlinked',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'device_hint' => $clockPoint->display_device_hint,
                    'reason' => 'admin_unlink',
                ],
            );

            return $locked;
        });
    }
}
