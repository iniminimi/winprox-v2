<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Models\HardwareDevice;
use App\Support\Ops\HardwareMac;

final class ResolveHardwareDeviceByMacAction
{
    public function handle(?string $macOrHint): ?HardwareDevice
    {
        $mac = HardwareMac::normalize($macOrHint);
        if ($mac === null) {
            return null;
        }

        return HardwareDevice::query()->where('mac', $mac)->first();
    }
}
