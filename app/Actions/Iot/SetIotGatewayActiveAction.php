<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotGateway;
use App\Support\Audit\AuditRecorder;

class SetIotGatewayActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IotGateway $gateway, bool $isActive, ?int $actorUserId = null): IotGateway
    {
        $gateway->forceFill(['is_active' => $isActive])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $gateway->tenant_id,
            action: $isActive ? 'iot_gateway.activated' : 'iot_gateway.deactivated',
            modelType: IotGateway::class,
            modelId: (int) $gateway->id,
            payload: ['is_active' => $isActive],
        );

        return $gateway->fresh();
    }
}
