<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class SetClockPointActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockPoint $clockPoint, bool $isActive, ?int $actorUserId): ClockPoint
    {
        if (! $isActive) {
            $tenant = Tenant::query()->find((int) $clockPoint->tenant_id);
            if ($tenant?->checkmateMode()) {
                throw new InvalidArgumentException('checkmate_cannot_deactivate_clock_point');
            }
        }

        $clockPoint->update(['is_active' => $isActive]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $clockPoint->tenant_id,
            action: $isActive ? 'clock_point.activated' : 'clock_point.deactivated',
            modelType: ClockPoint::class,
            modelId: $clockPoint->id,
            payload: ['clock_point_id' => $clockPoint->id, 'is_active' => $isActive],
        );

        return $clockPoint->fresh();
    }
}
