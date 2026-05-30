<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;

class SetTeamActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(InternalTeam $team, bool $active, ?int $actorUserId = null): InternalTeam
    {
        $team->update(['is_active' => $active]);

        $fresh = $team->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'team.updated',
            modelType: InternalTeam::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
