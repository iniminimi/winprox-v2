<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayMode;
use App\Models\ClockPoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateClockDisplayModeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockPoint $clockPoint,
        int $tenantId,
        ?int $actorUserId,
        ClockDisplayMode $mode,
    ): ClockPoint {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId, $mode) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $locked->update(['display_clock_mode' => $mode]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.display_clock_mode_updated',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                meta: ['mode' => $mode->value],
            );

            return $locked->fresh();
        });
    }
}
