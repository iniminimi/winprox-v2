<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureAnnouncementTranslationSlotsAction;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class ToggleLocationAnnouncementActiveAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureAnnouncementTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    public function handle(Announcement $announcement, ?int $actorUserId = null): void
    {
        $newStatus = ! (bool) $announcement->is_active;

        if ($newStatus) {
            Tenant::query()->findOrFail((int) $announcement->tenant_id)->assertCanActivateAnnouncement(
                $announcement->unit_id !== null ? (int) $announcement->unit_id : null,
                (int) $announcement->id,
            );
        }

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

        if ($newStatus) {
            $this->ensureTranslationSlots->handle($announcement->fresh());
        }
    }
}
