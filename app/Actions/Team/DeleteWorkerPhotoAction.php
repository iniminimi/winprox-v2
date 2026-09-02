<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteWorkerPhotoAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?int $actorUserId = null): Worker
    {
        $path = $worker->photo_path;
        if (! is_string($path) || $path === '') {
            return $worker;
        }

        $worker->update(['photo_path' => null]);
        Storage::disk('public')->delete($path);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.photo_deleted',
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
