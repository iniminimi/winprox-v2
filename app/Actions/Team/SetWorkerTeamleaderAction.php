<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

/**
 * Wijst de teamleader-vlag toe of trekt hem in. Een teamleader mag (in het
 * veld-portaal — aparte follow-up) iconen van collega-workers vrijgeven.
 */
class SetWorkerTeamleaderAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, bool $isTeamleader, ?int $actorUserId = null): Worker
    {
        $worker->update(['is_teamleader' => $isTeamleader]);

        $fresh = $worker->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.teamleader_changed',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'is_teamleader' => $fresh->is_teamleader],
        );

        return $fresh;
    }
}
