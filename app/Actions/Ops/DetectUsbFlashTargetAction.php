<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Data\Ops\UsbFlashSighting;
use App\Enums\HardwareDeviceType;
use App\Models\HardwareDevice;
use App\Support\Ops\HardwareMac;
use App\Support\Ops\UsbClockBridge;

final class DetectUsbFlashTargetAction
{
    public function __construct(
        private UsbClockBridge $usb,
        private ResolveHardwareDeviceByMacAction $byMac,
    ) {}

    public function handle(): UsbFlashSighting
    {
        set_time_limit(90);

        if (! $this->usb->available()) {
            return new UsbFlashSighting('unavailable');
        }

        $ports = $this->usb->clockPorts();
        if ($ports === []) {
            return new UsbFlashSighting('none');
        }
        if (count($ports) > 1) {
            return new UsbFlashSighting('ambiguous');
        }

        $mac = $this->usb->readMac($ports[0]);
        if ($mac === null) {
            return new UsbFlashSighting('unreadable');
        }

        $device = $this->byMac->handle($mac);

        return new UsbFlashSighting(
            status: 'found',
            typeLabel: $this->typeLabel($device),
            macDisplay: (string) HardwareMac::formatForDisplay($mac),
            mac: $mac,
        );
    }

    private function typeLabel(?HardwareDevice $device): string
    {
        $type = $device?->type ?? HardwareDeviceType::ClockDisplay;
        $label = __($type->labelKey());
        $model = trim((string) ($device?->model ?? ''));

        return $model !== '' ? $label.' '.$model : $label;
    }
}
