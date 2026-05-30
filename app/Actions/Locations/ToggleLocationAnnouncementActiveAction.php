<?php

namespace App\Actions\Locations;

use App\Models\Announcement;
use App\Support\Audit\AuditRecorder;

class ToggleLocationAnnouncementActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Announcement $announcement, ?int $actorUserId = null): void
    {
        $newStatus = ! (bool) $announcement->is_active;
        $announcement->is_active = $newStatus;
        if ($newStatus && $announcement->published_at === null) {
            $announcement->published_at = now();
        }
        $announcement->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $announcement->tenant_id,
            action: 'location_announcement.toggled',
            modelType: Announcement::class,
            modelId: (int) $announcement->id,
            payload: ['id' => $announcement->id, 'is_active' => $newStatus],
        );
    }
}
