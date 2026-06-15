<?php

namespace App\Actions\Locations;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class UpdateLocationAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{body: string, unit_id: ?int, is_active: bool, expires_at: ?string}  $data
     */
    public function handle(
        Location $location,
        Announcement $announcement,
        array $data,
        int $tenantId,
        ?int $actorUserId = null,
    ): Announcement {
        $unitId = $data['unit_id'] ?? null;
        if ($unitId !== null) {
            Unit::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $location->id)
                ->whereKey($unitId)
                ->firstOrFail();
        }

        $body = trim((string) $data['body']);
        $isActive = (bool) ($data['is_active'] ?? true);

        if ($isActive) {
            Tenant::query()->findOrFail($tenantId)->assertCanActivateAnnouncement(
                $unitId,
                (int) $announcement->id,
            );
        }

        $announcement->update([
            'title' => CreateLocationAnnouncementAction::titleFromBody($body),
            'body' => $body,
            'unit_id' => $unitId,
            'is_active' => $isActive,
            'published_at' => $isActive ? ($announcement->published_at ?? now()) : $announcement->published_at,
            'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_announcement.updated',
            modelType: Announcement::class,
            modelId: (int) $announcement->id,
            payload: ['id' => $announcement->id, 'location_id' => $location->id],
        );

        return $announcement;
    }
}
