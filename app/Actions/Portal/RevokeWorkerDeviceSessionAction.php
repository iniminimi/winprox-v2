<?php

namespace App\Actions\Portal;

use App\Models\WorkerDevice;

class RevokeWorkerDeviceSessionAction
{
    public function handle(string $deviceToken): void
    {
        $token = trim($deviceToken);
        if ($token === '') {
            return;
        }

        WorkerDevice::withoutGlobalScope('tenant')->where('device_token', $token)->delete();
    }
}
