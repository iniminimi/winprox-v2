<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;

class UpdateClockPointAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockPoint $clockPoint, array $data, ?int $actorUserId): ClockPoint
    {
        $locationId = $data['location_id'] ?? $clockPoint->location_id;
        if ($locationId !== null) {
            Location::query()
                ->where('tenant_id', $clockPoint->tenant_id)
                ->whereKey($locationId)
                ->firstOrFail();
        }

        $clockPoint->update([
            'name' => trim((string) ($data['name'] ?? $clockPoint->name)),
            'location_id' => $locationId,
            'sort_order' => (int) ($data['sort_order'] ?? $clockPoint->sort_order),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $clockPoint->tenant_id,
            action: 'clock_point.updated',
            modelType: ClockPoint::class,
            modelId: $clockPoint->id,
            payload: ['clock_point_id' => $clockPoint->id],
        );

        return $clockPoint->fresh(['location']);
    }
}
