<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aan-uren van het gekoppelde TFT-klokscherm (bv. crèche 06:00–19:00).
 * Beide leeg = altijd aan; over-middernacht (bv. 22:00–06:00) interpreteert
 * de firmware als venster over de middernachtsgrens.
 */
class UpdateClockDisplayScheduleAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
        ?string $from,
        ?string $until,
    ): ClockPoint {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        // Beide leeg (altijd aan) of beide gezet — nooit één open uiteinde.
        if (($from === null) !== ($until === null)) {
            throw new InvalidArgumentException('schedule_incomplete');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId, $from, $until) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $locked->update([
                'display_on_from' => $from,
                'display_on_until' => $until,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_schedule_updated',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'display_on_from' => $from,
                    'display_on_until' => $until,
                ],
            );

            return $locked;
        });
    }
}
