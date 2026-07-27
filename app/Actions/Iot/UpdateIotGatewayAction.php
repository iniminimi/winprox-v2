<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotGateway;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class UpdateIotGatewayAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IotGateway $gateway, string $name, ?int $actorUserId = null): IotGateway
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('iot.errors.name_required')],
            ]);
        }

        $gateway->forceFill(['name' => $name])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $gateway->tenant_id,
            action: 'iot_gateway.updated',
            modelType: IotGateway::class,
            modelId: (int) $gateway->id,
            payload: ['id' => $gateway->id, 'name' => $name],
        );

        return $gateway->fresh();
    }
}
