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

    public function handle(Worker $worker, UploadedFile $photo, ?int $actorUserId = null): Worker
    {
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
            payload: [
                'id' => $worker->id,
                'path' => $path,
            ],
        );

        return $worker->fresh();
    }
}
