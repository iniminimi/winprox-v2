<?php

namespace App\Actions\Time;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

class ClearWorkerClockDeviceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?int $actorUserId = null): Worker
    {
        $previous = $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null;

        $worker->forceFill(['clock_device_id' => null])->save();

        $fresh = $worker->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.clock_device_cleared',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: [
                'worker_id' => (int) $fresh->id,
                'previous_device_id' => $previous,
            ],
        );

        return $fresh;
    }
}
