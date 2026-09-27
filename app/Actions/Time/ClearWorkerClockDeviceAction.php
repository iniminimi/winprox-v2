<?php

namespace App\Actions\Time;

use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class ClearWorkerClockDeviceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, int $tenantId, ?int $actorUserId = null): Worker
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $previous = $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null;

        $worker->forceFill(['clock_device_id' => null])->save();

        // Oude toestelrijen doodmaken: anders blijft het token op de gsm als
        // "vreemd toestel" tellen voor andere uitvoerders na de vrijgave.
        $revoked = WorkerDevice::withoutGlobalScope('tenant')
            ->where('tenant_id', (int) $worker->tenant_id)
            ->where('worker_id', $worker->id)
            ->delete();

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
                'devices_revoked' => $revoked,
            ],
        );

        return $fresh;
    }
}
