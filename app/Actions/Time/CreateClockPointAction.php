<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CreateClockPointAction
{
    public function __construct(
        private ScheduleClockPointQrRecommendationAction $scheduleRecommendation,
    ) {}

    public function handle(Tenant $tenant, array $data, ?int $actorUserId): ClockPoint
    {
        $locationId = $data['location_id'] ?? null;
        if ($locationId !== null) {
            Location::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($locationId)
                ->firstOrFail();
        }

        $clockPoint = ClockPoint::create([
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'name' => trim((string) $data['name']),
            'qr_token' => Str::lower(Str::random(40)),
            'qr_renewed_at' => now(),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        if ($tenant->effectiveTimeQrRotationMonths() !== null) {
            $this->scheduleRecommendation->handle($clockPoint, $tenant, $clockPoint->qr_renewed_at);
        }

        return $clockPoint->fresh(['location']);
    }
}
