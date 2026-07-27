<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotGateway;
use App\Support\Audit\AuditRecorder;

class RotateIotGatewayTokenAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{gateway: IotGateway, plain_token: string}
     */
    public function handle(IotGateway $gateway, ?int $actorUserId = null): array
    {
        $issued = IotGateway::issueCredentials($gateway->name);

        $gateway->forceFill([
            'token_hash' => $issued['gateway']->token_hash,
            'token_prefix' => $issued['gateway']->token_prefix,
        ])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $gateway->tenant_id,
            action: 'iot_gateway.token_rotated',
            modelType: IotGateway::class,
            modelId: (int) $gateway->id,
            payload: [
                'id' => $gateway->id,
                'token_prefix' => $gateway->token_prefix,
            ],
        );

        return [
            'gateway' => $gateway->fresh(),
            'plain_token' => $issued['plain_token'],
        ];
    }
}
