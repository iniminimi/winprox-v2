<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Models\WorkerDevice;

class AttachWorkerDeviceAction
{
    /**
     * @return array{worker: Worker, device_token: string}
     */
    public function handle(Worker $worker): array
    {
        $device = WorkerDevice::create([
            'tenant_id' => $worker->tenant_id,
            'worker_id' => $worker->id,
            'device_token' => WorkerDevice::generateToken(),
            'last_seen_at' => now(),
        ]);

        return ['worker' => $worker, 'device_token' => $device->device_token];
    }
}
