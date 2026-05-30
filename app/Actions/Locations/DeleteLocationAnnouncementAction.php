<?php

namespace App\Actions\Locations;

use App\Models\Announcement;
use App\Support\Audit\AuditRecorder;

class DeleteLocationAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Announcement $announcement, ?int $actorUserId = null): void
    {
        $tenantId = (int) $announcement->tenant_id;
        $announcementId = (int) $announcement->id;
        $locationId = (int) $announcement->location_id;

        $announcement->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_announcement.deleted',
            modelType: Announcement::class,
            modelId: $announcementId,
            payload: ['id' => $announcementId, 'location_id' => $locationId],
        );
    }
}
