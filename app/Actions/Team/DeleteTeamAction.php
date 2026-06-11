<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class DeleteTeamAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(InternalTeam $team, ?int $actorUserId = null): void
    {
        if ($team->workers()->exists()) {
            throw new InvalidArgumentException('team_has_workers');
        }

        $tenantId = (int) $team->tenant_id;
        $teamId = (int) $team->id;
        $name = (string) $team->name;

        $team->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team.deleted',
            modelType: InternalTeam::class,
            modelId: $teamId,
            payload: ['id' => $teamId, 'name' => $name],
        );
    }
}
