<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Models\WorkerDevice;
use InvalidArgumentException;

/**
 * Eigen uniek toestel-token voor deze browser. Kopieert nooit het token van
 * een andere telefoon. Bestaande cookie van een andere worker blijft staan
 * (prikken weigert dan); anders nieuw token.
 */
class EnsureWorkerPortalDeviceAction
{
    public function __construct(
        private AttachWorkerDeviceAction $attachDevice,
        private TouchWorkerDeviceAction $touchDevice,
    ) {}

    public function handle(Worker $worker, string $requestToken, int $tenantId): ?WorkerDevice
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $token = trim($requestToken);
        if ($token !== '') {
            $existing = WorkerDevice::withoutGlobalScope('tenant')
                ->where('device_token', $token)
                ->first();

            if ($existing !== null && (int) $existing->worker_id === (int) $worker->id) {
                $this->touchDevice->handle($existing);

                return $existing;
            }

            if ($existing !== null) {
                return null;
            }
        }

        $result = $this->attachDevice->handle($worker);

        return $worker->devices()->where('device_token', $result['device_token'])->first();
    }
}
