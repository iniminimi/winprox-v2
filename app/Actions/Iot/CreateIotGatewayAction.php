<?php

declare(strict_types=1);

namespace App\Actions\Iot;

use App\Models\IotGateway;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Iot\IotModuleAccess;
use Illuminate\Validation\ValidationException;

class CreateIotGatewayAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{gateway: IotGateway, plain_token: string}
     */
    public function handle(string $name, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! IotModuleAccess::tenantHasModule($tenant)) {
            throw ValidationException::withMessages([
                'name' => [__('iot.errors.module_disabled')],
            ]);
        }

        $issued = IotGateway::issueCredentials($name);
        /** @var IotGateway $gateway */
        $gateway = $issued['gateway'];
        $gateway->tenant_id = $tenantId;
        $gateway->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'iot_gateway.created',
            modelType: IotGateway::class,
            modelId: (int) $gateway->id,
            payload: [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'token_prefix' => $gateway->token_prefix,
            ],
        );

        return [
            'gateway' => $gateway->fresh(),
            'plain_token' => $issued['plain_token'],
        ];
    }
}
