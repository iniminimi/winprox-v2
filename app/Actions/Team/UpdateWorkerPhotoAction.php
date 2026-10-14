<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\WorkerPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateWorkerPhotoAction
{
    public function __construct(
        private WorkerPhotoStorage $storage,
        private AuditRecorder $audit,
    ) {}

    public function handle(Worker $worker, UploadedFile $photo, ?int $actorUserId = null, ?Worker $actorWorker = null): Worker
    {
        if ($actorWorker !== null) {
            if (! $actorWorker->is_teamleader || ! $actorWorker->is_active) {
                throw new \InvalidArgumentException('not_teamleader');
            }

            if ((int) $actorWorker->internal_team_id !== (int) $worker->internal_team_id) {
                throw new \InvalidArgumentException('wrong_team');
            }

            if (! $worker->is_active) {
                throw new \InvalidArgumentException('worker_inactive');
            }
        }

        $previous = $worker->photo_path;
        $path = $this->storage->storePrecompressedCopy($photo);

        $worker->update(['photo_path' => $path]);

        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.photo_updated',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: array_merge(
                [
                    'id' => $worker->id,
                    'path' => $path,
                ],
                $actorWorker !== null ? ['actor_worker_id' => $actorWorker->id] : [],
            ),
        );

        return $worker->fresh();
    }
}
