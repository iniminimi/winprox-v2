<?php

namespace App\Actions\Locations;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class CreateLocationAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, unit_id: ?int, is_active: bool, expires_at: ?string}  $data
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): Announcement
    {
        $unitId = $data['unit_id'] ?? null;
        if ($unitId !== null) {
            Unit::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $location->id)
                ->whereKey($unitId)
                ->firstOrFail();
        }

        $description = trim((string) $data['description']);
        $isActive = (bool) ($data['is_active'] ?? true);
        $expiresAt = ! empty($data['expires_at']) ? $data['expires_at'] : null;

        if ($isActive) {
            Tenant::query()->findOrFail($tenantId)->assertCanActivateAnnouncement($unitId);
        }

        $announcement = Announcement::create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'unit_id' => $unitId,
            'description' => $description,
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_announcement.created',
            modelType: Announcement::class,
            modelId: (int) $announcement->id,
            payload: ['id' => $announcement->id, 'location_id' => $location->id],
        );

        return $announcement;
    }
}
