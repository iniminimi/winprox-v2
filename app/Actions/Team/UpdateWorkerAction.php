<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

class UpdateWorkerAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Worker $worker, array $data, ?int $actorUserId = null): Worker
    {
        $worker->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.updated',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: ['id' => $worker->id, 'internal_team_id' => $worker->internal_team_id],
        );

        return $worker->fresh();
    }
}
