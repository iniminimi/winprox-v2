<?php

namespace App\Actions\Team;

use App\Models\TeamAnnouncement;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;

class UpdateTeamAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

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

        $isActive = (bool) ($data['is_active'] ?? true);
        $expires = $data['expires_at'] ?? null;

        $announcement->update([
            'description' => trim((string) $data['description']),
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
            payload: ['id' => $announcement->id, 'worker_id' => $workerId],
        );

        return $announcement->fresh();
    }
}
