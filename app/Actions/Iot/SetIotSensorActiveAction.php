<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotSensor;
use App\Support\Audit\AuditRecorder;

class SetIotSensorActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IotSensor $sensor, bool $isActive, ?int $actorUserId = null): IotSensor
    {
        $sensor->forceFill(['is_active' => $isActive])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $sensor->tenant_id,
            action: $isActive ? 'iot_sensor.activated' : 'iot_sensor.deactivated',
            modelType: IotSensor::class,
            modelId: (int) $sensor->id,
            payload: ['is_active' => $isActive],
        );

        return $sensor->fresh();
    }
}
