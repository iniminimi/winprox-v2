<?php

namespace App\Actions\Team;

use App\Models\TeamAnnouncement;
use App\Support\Audit\AuditRecorder;

class DeleteTeamAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(TeamAnnouncement $announcement, int $tenantId, ?int $actorUserId = null): void
    {
        $id = (int) $announcement->id;
        $announcement->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_announcement.deleted',
            modelType: TeamAnnouncement::class,
            modelId: $id,
            payload: ['id' => $id],
        );
    }
}
