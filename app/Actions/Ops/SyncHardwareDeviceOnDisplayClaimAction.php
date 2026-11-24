<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Actions\Audit\LogAuditAction;
use App\Enums\HardwareDeviceEventType;
use App\Enums\HardwareDeviceStatus;
use App\Models\ClockPoint;
use App\Models\HardwareDevice;
use App\Support\Ops\HardwareMac;
use Illuminate\Support\Facades\DB;

/**
 * Na bevestigde schermkoppeling: inventarisrij (via MAC) markeren als in gebruik
 * bij die tenant / dat clock point. Geen inventarisrij → no-op.
 */
final class SyncHardwareDeviceOnDisplayClaimAction
{
    public function __construct(
        private RecordHardwareDeviceEventAction $recordEvent,
        private LogAuditAction $audit,
    ) {}

    public function handle(ClockPoint $point, ?string $deviceHint, ?int $actorUserId): ?HardwareDevice
    {
        $mac = HardwareMac::normalize($deviceHint);
        if ($mac === null) {
            return null;
        }

        return DB::transaction(function () use ($point, $mac, $actorUserId) {
            $device = HardwareDevice::query()->where('mac', $mac)->lockForUpdate()->first();
            if ($device === null) {
                return null;
            }

            $previousTenantId = $device->tenant_id !== null ? (int) $device->tenant_id : null;
            $previousPointId = $device->clock_point_id !== null ? (int) $device->clock_point_id : null;
            $tenantId = (int) $point->tenant_id;
            $pointId = (int) $point->id;

            $updates = [
                'tenant_id' => $tenantId,
                'clock_point_id' => $pointId,
                'status' => HardwareDeviceStatus::InUse->value,
                'last_seen_at' => now(),
                'returned_at' => null,
            ];

            if ($previousTenantId !== $tenantId) {
                $updates['assigned_to_tenant_at'] = now();
            } elseif ($device->assigned_to_tenant_at === null) {
                $updates['assigned_to_tenant_at'] = now();
            }

            if ($previousPointId !== $pointId) {
                $updates['installed_at'] = now();
            } elseif ($device->installed_at === null) {
                $updates['installed_at'] = now();
            }

            $device->update($updates);

            if ($previousTenantId !== $tenantId) {
                $this->recordEvent->handle(
                    device: $device->fresh(),
                    type: HardwareDeviceEventType::Assigned,
                    actorUserId: $actorUserId,
                    tenantId: $tenantId,
                    clockPointId: $pointId,
                    payload: [
                        'previous_tenant_id' => $previousTenantId,
                        'source' => 'display_claim',
                    ],
                );
            }

            if ($previousPointId !== null && $previousPointId !== $pointId) {
                $this->recordEvent->handle(
                    device: $device->fresh(),
                    type: HardwareDeviceEventType::Uninstalled,
                    actorUserId: $actorUserId,
                    tenantId: $previousTenantId,
                    clockPointId: $previousPointId,
                    payload: ['source' => 'display_claim'],
                );
            }

            if ($previousPointId !== $pointId) {
                $this->recordEvent->handle(
                    device: $device->fresh(),
                    type: HardwareDeviceEventType::Installed,
                    actorUserId: $actorUserId,
                    tenantId: $tenantId,
                    clockPointId: $pointId,
                    payload: ['source' => 'display_claim'],
                );
            }

            $this->audit->handle(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'hardware_device.synced_from_claim',
                modelType: HardwareDevice::class,
                modelId: (int) $device->id,
                payload: [
                    'serial_number' => $device->serial_number,
                    'mac' => $mac,
                    'clock_point_id' => $pointId,
                ],
            );

            return $device->fresh();
        });
    }
}
