<?php

namespace App\Actions\Notifications;

use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Audit\AuditRecorder;

class SaveWorkerPushSubscriptionAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        string $deviceToken,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $locale,
    ): bool {
        $token = trim($deviceToken);
        if ($token === '') {
            return false;
        }

        $device = WorkerDevice::withoutGlobalScope('tenant')
            ->where('device_token', $token)
            ->first();

        if (! $device instanceof WorkerDevice) {
            return false;
        }

        $worker = Worker::withoutGlobalScope('tenant')
            ->where('tenant_id', $device->tenant_id)
            ->whereKey($device->worker_id)
            ->first();

        if (! $worker instanceof Worker || ! $worker->is_active) {
            return false;
        }

        if ($worker->clock_device_id === null || (int) $worker->clock_device_id !== (int) $device->id) {
            return false;
        }

        $hash = hash('sha256', $endpoint);

        WorkerDevice::withoutGlobalScope('tenant')
            ->where('push_endpoint_hash', $hash)
            ->whereKeyNot($device->id)
            ->get()
            ->each(function (WorkerDevice $other): void {
                $other->forceFill([
                    'push_endpoint' => null,
                    'push_endpoint_hash' => null,
                    'push_public_key' => null,
                    'push_auth_token' => null,
                    'push_locale' => null,
                ])->save();
            });

        $device->forceFill([
            'push_endpoint' => $endpoint,
            'push_endpoint_hash' => $hash,
            'push_public_key' => $publicKey,
            'push_auth_token' => $authToken,
            'push_locale' => $locale,
        ])->save();

        $this->audit->record(
            userId: null,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.push_subscribed',
            modelType: WorkerDevice::class,
            modelId: (int) $device->id,
            payload: [
                'worker_id' => (int) $worker->id,
                'device_id' => (int) $device->id,
            ],
        );

        return true;
    }
}
