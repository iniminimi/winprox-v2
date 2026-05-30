<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;

class UpdateTeamAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data, ?int $actorUserId = null): InternalTeam
    {
        $team->update([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $team->is_active),
        ]);

        $fresh = $team->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'team.updated',
            modelType: InternalTeam::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
