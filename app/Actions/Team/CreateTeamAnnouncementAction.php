<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Models\TeamAnnouncement;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Carbon;

class CreateTeamAnnouncementAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, worker_id: ?int, is_active: bool, expires_at: ?string, original_language?: ?string}  $data
     */
    public function handle(InternalTeam $team, array $data, int $tenantId, ?int $actorUserId = null): TeamAnnouncement
    {
        $workerId = $data['worker_id'] ?? null;
        if ($workerId !== null) {
            Worker::query()
                ->where('tenant_id', $tenantId)
                ->where('internal_team_id', $team->id)
                ->whereKey($workerId)
                ->firstOrFail();
        }

        $isActive = (bool) ($data['is_active'] ?? true);

        $announcement = TeamAnnouncement::create([
            'tenant_id' => $tenantId,
            'internal_team_id' => $team->id,
            'worker_id' => $workerId,
            'description' => trim((string) $data['description']),
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
            'expires_at' => $this->expires($data['expires_at'] ?? null),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_announcement.created',
            modelType: TeamAnnouncement::class,
            modelId: (int) $announcement->id,
            payload: [
                'id' => $announcement->id,
                'internal_team_id' => $team->id,
                'worker_id' => $workerId,
            ],
        );

        return $announcement;
    }

    private function expires(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->endOfDay();
    }
}
