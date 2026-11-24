<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Enums\HardwareDeviceEventType;
use App\Models\HardwareDevice;
use App\Models\HardwareDeviceEvent;

final class RecordHardwareDeviceEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        HardwareDevice $device,
        HardwareDeviceEventType $type,
        ?int $actorUserId,
        ?int $tenantId = null,
        ?int $clockPointId = null,
        array $payload = [],
    ): HardwareDeviceEvent {
        return HardwareDeviceEvent::query()->create([
            'hardware_device_id' => $device->id,
            'event_type' => $type->value,
            'tenant_id' => $tenantId ?? $device->tenant_id,
            'clock_point_id' => $clockPointId ?? $device->clock_point_id,
            'actor_user_id' => $actorUserId,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
