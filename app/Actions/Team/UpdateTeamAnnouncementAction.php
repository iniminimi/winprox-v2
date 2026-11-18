<?php

namespace App\Actions\Team;

use App\Models\TeamAnnouncement;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;

class UpdateTeamAnnouncementAction
{
    public function __construct(
        private AuditRecorder $audit,
        private NotifyWorkersOfVisibleLibraryAction $notify,
    ) {}

    /**
     * @param  array{description: string, worker_id: ?int, is_active: bool, expires_at: ?string}  $data
     */
    public function handle(TeamAnnouncement $announcement, array $data, int $tenantId, ?int $actorUserId = null): TeamAnnouncement
    {
        $workerId = $data['worker_id'] ?? null;
        if ($workerId !== null) {
            Worker::query()
                ->where('tenant_id', $tenantId)
                ->where('internal_team_id', $announcement->internal_team_id)
                ->whereKey($workerId)
                ->firstOrFail();
        }

        $wasVisible = $announcement->is_active && ! $announcement->isExpired();
        $previousWorkerId = $announcement->worker_id !== null ? (int) $announcement->worker_id : null;
        $isActive = (bool) ($data['is_active'] ?? true);
        $expires = $data['expires_at'] ?? null;
        $description = trim((string) $data['description']);
        $textChanged = $description !== (string) $announcement->description;

        $announcement->update([
            ...($textChanged ? ['opened_worker_ids' => null, 'is_revised' => true] : []),
            'description' => $description,
            'worker_id' => $workerId,
            'is_active' => $isActive,
            'published_at' => $isActive ? ($announcement->published_at ?? now()) : null,
            'expires_at' => ($expires === null || trim((string) $expires) === '')
                ? null
                : Carbon::parse($expires)->endOfDay(),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_announcement.updated',
            modelType: TeamAnnouncement::class,
            modelId: (int) $announcement->id,
            payload: ['id' => $announcement->id, 'worker_id' => $workerId, 'opens_reset' => $textChanged],
        );

        $fresh = $announcement->fresh() ?? $announcement;
        $nowWorkerId = $fresh->worker_id !== null ? (int) $fresh->worker_id : null;
        $nowVisible = $fresh->is_active && ! $fresh->isExpired();
        if ($nowVisible && (! $wasVisible || $previousWorkerId !== $nowWorkerId)) {
            $this->notify->handleAnnouncement($fresh);
        }

        return $fresh;
    }
}
