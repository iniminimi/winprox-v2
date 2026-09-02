<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteWorkerAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?int $actorUserId = null, ?Worker $actorWorker = null): void
    {
        if ($actorWorker !== null) {
            if (! $actorWorker->is_teamleader || ! $actorWorker->is_active) {
                throw new \InvalidArgumentException('not_teamleader');
            }

            if ((int) $actorWorker->internal_team_id !== (int) $worker->internal_team_id) {
                throw new \InvalidArgumentException('wrong_team');
            }

            if ((int) $actorWorker->id === (int) $worker->id) {
                throw new \InvalidArgumentException('cannot_delete_self');
            }
        }

        $tenantId = (int) $worker->tenant_id;
        $id = (int) $worker->id;
        $photoPath = $worker->photo_path;

        $worker->devices()->delete();
        $worker->delete();

        if (is_string($photoPath) && $photoPath !== '') {
            Storage::disk('public')->delete($photoPath);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.deleted',
            modelType: Worker::class,
            modelId: $id,
            payload: array_merge(
                ['id' => $id],
                $actorWorker !== null ? ['actor_worker_id' => $actorWorker->id] : []
            ),
        );
    }
}
