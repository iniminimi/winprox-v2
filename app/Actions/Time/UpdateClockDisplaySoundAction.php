<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Geluid van het gekoppelde TFT-klokscherm aan of uit (piep bij elke toets
 * en na een prikking). Het scherm neemt de keuze over bij de volgende ping.
 */
class UpdateClockDisplaySoundAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
        bool $enabled,
    ): ClockPoint {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId, $enabled) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $locked->update(['display_sound_enabled' => $enabled]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_sound_updated',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'display_sound_enabled' => $enabled,
                ],
            );

            return $locked;
        });
    }
}
