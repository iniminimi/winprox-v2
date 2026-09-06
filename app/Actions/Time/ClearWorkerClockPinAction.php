<?php

namespace App\Actions\Time;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class ClearWorkerClockPinAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, int $tenantId, ?int $actorUserId = null): Worker
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $worker->forceFill([
            'clock_pin_hash' => null,
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        $fresh = $worker->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.clock_pin_cleared',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: ['worker_id' => (int) $fresh->id],
        );

        return $fresh;
    }
}
