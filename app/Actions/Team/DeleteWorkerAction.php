<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

class DeleteWorkerAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?int $actorUserId = null): void
    {
        $tenantId = (int) $worker->tenant_id;
        $id = (int) $worker->id;

        $worker->devices()->delete();
        $worker->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.deleted',
            modelType: Worker::class,
            modelId: $id,
            payload: ['id' => $id],
        );
    }
}
