<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Secret-rotatie bij vermoedelijke compromis. Draait display_secret én de
 * wpclk_-device-token — het gekoppelde scherm kan daarna geen geldige QR meer
 * maken en geen ping meer doen (401 → pairing-modus). Er is geen veilige weg
 * om de nieuwe secret op het oude toestel te krijgen; her-pair is vereist.
 */
class RotateClockPointDisplaySecretAction
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

            $plainToken = 'wpclk_'.Str::lower(Str::random(40));

            $locked->update([
                'display_secret' => bin2hex(random_bytes(32)),
                'display_token_hash' => hash('sha256', $plainToken),
                'display_token_prefix' => substr($plainToken, 0, 12),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_secret_rotated',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'device_hint' => $locked->display_device_hint,
                ],
            );

            return $locked;
        });
    }
}
