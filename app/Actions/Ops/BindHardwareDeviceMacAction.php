<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Data\Ops\BindHardwareDeviceMacData;
use App\Enums\HardwareDeviceEventType;
use App\Models\HardwareDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BindHardwareDeviceMacAction
{
    public function __construct(
        private RecordHardwareDeviceEventAction $recordEvent,
        private LogAuditAction $audit,
    ) {}

    public function handle(HardwareDevice $device, BindHardwareDeviceMacData $data, int $actorUserId): HardwareDevice
    {
        return DB::transaction(function () use ($device, $data, $actorUserId) {
            $locked = HardwareDevice::query()->whereKey($device->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('device_not_found');
            }

            $taken = HardwareDevice::query()
                ->where('mac', $data->mac)
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw new InvalidArgumentException('mac_already_linked');
            }

            if ($locked->mac !== null && $locked->mac !== $data->mac) {
                throw new InvalidArgumentException('mac_already_set');
            }

            $locked->update([
                'mac' => $data->mac,
                'mac_set_at' => $locked->mac_set_at ?? now(),
            ]);

            $this->recordEvent->handle(
                device: $locked,
                type: HardwareDeviceEventType::MacLinked,
                actorUserId: $actorUserId,
                payload: ['mac' => $data->mac],
            );

            $this->audit->handle(
                userId: $actorUserId,
                tenantId: $locked->tenant_id !== null ? (int) $locked->tenant_id : null,
                action: 'hardware_device.mac_linked',
                modelType: HardwareDevice::class,
                modelId: (int) $locked->id,
                payload: [
                    'serial_number' => $locked->serial_number,
                    'mac' => $data->mac,
                ],
            );

            return $locked->fresh();
        });
    }
}
