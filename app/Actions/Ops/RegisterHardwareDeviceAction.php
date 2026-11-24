<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Enums\HardwareDeviceEventType;
use App\Enums\HardwareDeviceStatus;
use App\Models\HardwareDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RegisterHardwareDeviceAction
{
    public function __construct(
        private RecordHardwareDeviceEventAction $recordEvent,
        private LogAuditAction $audit,
    ) {}

    public function handle(RegisterHardwareDeviceData $data, int $actorUserId): HardwareDevice
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $exists = HardwareDevice::query()
                ->where('serial_number', $data->serialNumber)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('serial_already_registered');
            }

            $device = HardwareDevice::query()->create([
                'brand' => $data->brand,
                'type' => $data->type->value,
                'model' => $data->model,
                'sku' => $data->sku,
                'serial_number' => $data->serialNumber,
                'status' => HardwareDeviceStatus::InStock->value,
                'condition' => $data->condition->value,
                'sn_scanned_at' => now(),
                'purchase_ref' => $data->purchaseRef,
                'notes' => $data->notes,
            ]);

            $this->recordEvent->handle(
                device: $device,
                type: HardwareDeviceEventType::Received,
                actorUserId: $actorUserId,
                payload: ['serial_number' => $device->serial_number],
            );

            $this->audit->handle(
                userId: $actorUserId,
                tenantId: null,
                action: 'hardware_device.registered',
                modelType: HardwareDevice::class,
                modelId: (int) $device->id,
                payload: [
                    'serial_number' => $device->serial_number,
                    'brand' => $device->brand,
                    'type' => $device->type->value,
                    'sku' => $device->sku,
                ],
            );

            return $device;
        });
    }
}
