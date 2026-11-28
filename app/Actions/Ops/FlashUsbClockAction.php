<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Data\Ops\UsbFlashSighting;
use App\Enums\HardwareDeviceEventType;
use App\Support\Ops\UsbClockBridge;

final class FlashUsbClockAction
{
    public function __construct(
        private UsbClockBridge $usb,
        private DetectUsbFlashTargetAction $detect,
        private RecordHardwareDeviceEventAction $events,
        private ResolveHardwareDeviceByMacAction $byMac,
        private LogAuditAction $audit,
    ) {}

    public function handle(string $expectedMac, int $actorUserId): UsbFlashSighting
    {
        set_time_limit(320);

        $seen = $this->detect->handle();
        if ($seen->status !== 'found') {
            return $seen;
        }
        if ($seen->mac !== strtoupper($expectedMac)) {
            return new UsbFlashSighting('changed', $seen->typeLabel, $seen->macDisplay, $seen->mac);
        }

        $ports = $this->usb->clockPorts();
        if (count($ports) !== 1) {
            return new UsbFlashSighting('changed', $seen->typeLabel, $seen->macDisplay, $seen->mac);
        }

        $ok = $this->usb->flash($ports[0]);
        $device = $this->byMac->handle($seen->mac);

        if ($device !== null) {
            $this->events->handle(
                device: $device,
                type: HardwareDeviceEventType::Flashed,
                actorUserId: $actorUserId,
                payload: ['mac' => $seen->mac, 'ok' => $ok],
            );
        }

        $this->audit->handle(
            userId: $actorUserId,
            tenantId: $device?->tenant_id !== null ? (int) $device->tenant_id : null,
            action: $ok ? 'hardware_device.flashed' : 'hardware_device.flash_failed',
            modelType: $device !== null ? $device::class : null,
            modelId: $device !== null ? (int) $device->id : null,
            payload: ['mac' => $seen->mac],
        );

        return new UsbFlashSighting(
            status: $ok ? 'ok' : 'failed',
            typeLabel: $seen->typeLabel,
            macDisplay: $seen->macDisplay,
            mac: $seen->mac,
        );
    }
}
