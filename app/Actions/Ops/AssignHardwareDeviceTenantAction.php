<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Data\Ops\AssignHardwareDeviceTenantData;
use App\Enums\HardwareDeviceEventType;
use App\Enums\HardwareDeviceStatus;
use App\Models\HardwareDevice;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignHardwareDeviceTenantAction
{
    public function __construct(
        private RecordHardwareDeviceEventAction $recordEvent,
        private LogAuditAction $audit,
    ) {}

    public function handle(HardwareDevice $device, AssignHardwareDeviceTenantData $data, int $actorUserId): HardwareDevice
    {
        return DB::transaction(function () use ($device, $data, $actorUserId) {
            $locked = HardwareDevice::query()->whereKey($device->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('device_not_found');
            }

            $previousTenantId = $locked->tenant_id !== null ? (int) $locked->tenant_id : null;
            $nextTenantId = $data->tenantId;

            if ($nextTenantId !== null && ! Tenant::query()->whereKey($nextTenantId)->exists()) {
                throw new InvalidArgumentException('tenant_not_found');
            }

            if ($previousTenantId === $nextTenantId) {
                return $locked;
            }

            $updates = [
                'tenant_id' => $nextTenantId,
            ];

            if ($nextTenantId === null) {
                $updates['assigned_to_tenant_at'] = null;
                $updates['clock_point_id'] = null;
                $updates['installed_at'] = null;
                $updates['returned_at'] = now();
                if (! in_array($locked->status, [HardwareDeviceStatus::Retired, HardwareDeviceStatus::Lost], true)) {
                    $updates['status'] = HardwareDeviceStatus::Returned->value;
                }
            } else {
                $updates['assigned_to_tenant_at'] = now();
                $updates['returned_at'] = null;
                if ($locked->status === HardwareDeviceStatus::InStock
                    || $locked->status === HardwareDeviceStatus::Returned) {
                    $updates['status'] = HardwareDeviceStatus::Assigned->value;
                }
            }

            $locked->update($updates);

            $this->recordEvent->handle(
                device: $locked->fresh(),
                type: $nextTenantId === null
                    ? HardwareDeviceEventType::Unassigned
                    : HardwareDeviceEventType::Assigned,
                actorUserId: $actorUserId,
                tenantId: $nextTenantId,
                payload: [
                    'previous_tenant_id' => $previousTenantId,
                    'tenant_id' => $nextTenantId,
                ],
            );

            if ($nextTenantId === null) {
                $this->recordEvent->handle(
                    device: $locked->fresh(),
                    type: HardwareDeviceEventType::Returned,
                    actorUserId: $actorUserId,
                    tenantId: $previousTenantId,
                    payload: ['previous_tenant_id' => $previousTenantId],
                );
            }

            $this->audit->handle(
                userId: $actorUserId,
                tenantId: $nextTenantId ?? $previousTenantId,
                action: $nextTenantId === null
                    ? 'hardware_device.unassigned'
                    : 'hardware_device.assigned',
                modelType: HardwareDevice::class,
                modelId: (int) $locked->id,
                payload: [
                    'serial_number' => $locked->serial_number,
                    'previous_tenant_id' => $previousTenantId,
                    'tenant_id' => $nextTenantId,
                ],
            );

            return $locked->fresh();
        });
    }
}
