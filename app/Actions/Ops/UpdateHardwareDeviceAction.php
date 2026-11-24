<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Data\Ops\UpdateHardwareDeviceData;
use App\Enums\HardwareDeviceEventType;
use App\Enums\HardwareDeviceStatus;
use App\Models\HardwareDevice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateHardwareDeviceAction
{
    public function __construct(
        private RecordHardwareDeviceEventAction $recordEvent,
        private LogAuditAction $audit,
    ) {}

    public function handle(HardwareDevice $device, UpdateHardwareDeviceData $data, int $actorUserId): HardwareDevice
    {
        return DB::transaction(function () use ($device, $data, $actorUserId) {
            $locked = HardwareDevice::query()->whereKey($device->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('device_not_found');
            }

            $updates = [
                'brand' => $data->brand,
                'type' => $data->type->value,
                'model' => $data->model,
                'sku' => $data->sku,
                'status' => $data->status->value,
                'condition' => $data->condition->value,
                'purchase_ref' => $data->purchaseRef,
                'notes' => $data->notes,
            ];

            if ($data->status === HardwareDeviceStatus::Retired) {
                $updates['retired_at'] = $locked->retired_at ?? now();
            }
            if ($data->status === HardwareDeviceStatus::Lost) {
                $updates['retired_at'] = $locked->retired_at ?? now();
            }
            if ($data->status === HardwareDeviceStatus::Returned) {
                $updates['returned_at'] = $locked->returned_at ?? now();
                $updates['clock_point_id'] = null;
                $updates['installed_at'] = null;
            }

            $locked->update($updates);

            $this->recordEvent->handle(
                device: $locked->fresh(),
                type: match ($data->status) {
                    HardwareDeviceStatus::Retired => HardwareDeviceEventType::Retired,
                    HardwareDeviceStatus::Lost => HardwareDeviceEventType::Lost,
                    HardwareDeviceStatus::Returned => HardwareDeviceEventType::Returned,
                    default => HardwareDeviceEventType::Updated,
                },
                actorUserId: $actorUserId,
                payload: [
                    'status' => $data->status->value,
                    'sku' => $data->sku,
                ],
            );

            $this->audit->handle(
                userId: $actorUserId,
                tenantId: $locked->tenant_id !== null ? (int) $locked->tenant_id : null,
                action: 'hardware_device.updated',
                modelType: HardwareDevice::class,
                modelId: (int) $locked->id,
                payload: [
                    'serial_number' => $locked->serial_number,
                    'status' => $data->status->value,
                ],
            );

            return $locked->fresh();
        });
    }
}
