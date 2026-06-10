<?php

namespace App\Actions\Portal;

use App\Models\WorkerDevice;

class TouchWorkerDeviceAction
{
    public function handle(WorkerDevice $device): void
    {
        $device->forceFill(['last_seen_at' => now()])->save();
    }
}
