<?php

namespace App\Actions\Time;

use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Eén gsm per uitvoerder voor Time-prikacties. Nieuw toestel eerst vrijgeven
 * via beheer of teamleader. Elke poging met een ander toestel wordt gelogd.
 */
class AssertWorkerClockDeviceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?WorkerDevice $device, ?string $requestToken = null): WorkerDevice
    {
        $foreign = $this->foreignDeviceFromToken($worker, $requestToken);
        if ($foreign !== null) {
            $this->logRefusal($worker, $foreign, 'clock_device_foreign');

            throw new InvalidArgumentException('clock_device_mismatch');
        }

        if ($device === null) {
            if ($worker->clock_device_id !== null) {
                $this->logRefusal($worker, null, 'clock_device_missing');

                throw new InvalidArgumentException('clock_device_mismatch');
            }

            throw new InvalidArgumentException('clock_device_missing');
        }

        if ((int) $device->worker_id !== (int) $worker->id) {
            $this->logRefusal($worker, $device, 'clock_device_foreign');

            throw new InvalidArgumentException('clock_device_mismatch');
        }

        $boundId = $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null;

        if ($boundId === null) {
            $worker->forceFill(['clock_device_id' => $device->id])->save();

            $this->audit->record(
                userId: null,
                tenantId: (int) $worker->tenant_id,
                action: 'worker.clock_device_bound',
                modelType: Worker::class,
                modelId: (int) $worker->id,
                payload: [
                    'worker_id' => (int) $worker->id,
                    'device_id' => (int) $device->id,
                ],
            );

            return $device;
        }

        if ($boundId !== (int) $device->id) {
            $this->logRefusal($worker, $device, 'clock_device_mismatch');

            throw new InvalidArgumentException('clock_device_mismatch');
        }

        return $device;
    }

    private function foreignDeviceFromToken(Worker $worker, ?string $requestToken): ?WorkerDevice
    {
        $token = trim((string) $requestToken);
        if ($token === '') {
            return null;
        }

        $device = WorkerDevice::withoutGlobalScope('tenant')
            ->where('device_token', $token)
            ->first();

        if ($device === null) {
            return null;
        }

        return (int) $device->worker_id !== (int) $worker->id ? $device : null;
    }

    private function logRefusal(Worker $worker, ?WorkerDevice $attempted, string $reason): void
    {
        $this->audit->record(
            userId: null,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.clock_device_refused',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [
                'worker_id' => (int) $worker->id,
                'bound_device_id' => $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null,
                'attempted_device_id' => $attempted?->id,
                'attempted_worker_id' => $attempted !== null ? (int) $attempted->worker_id : null,
                'reason' => $reason,
            ],
        );
    }
}
