<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IotGateway;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IotGateway> */
class IotGatewayFactory extends Factory
{
    protected $model = IotGateway::class;

    public function definition(): array
    {
        $plain = 'wpiot_'.fake()->unique()->regexify('[a-z0-9]{40}');

        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Gateway '.fake()->word(),
            'token_hash' => IotGateway::hashToken($plain),
            'token_prefix' => substr($plain, 0, 12),
            'is_active' => true,
            'last_seen_at' => null,
        ];
    }

    /**
     * @return array{gateway: IotGateway, plain_token: string}
     */
    public function withPlainToken(): array
    {
        $plain = 'wpiot_'.fake()->unique()->regexify('[a-z0-9]{40}');
        $gateway = $this->state([
            'token_hash' => IotGateway::hashToken($plain),
            'token_prefix' => substr($plain, 0, 12),
        ])->create();

        return ['gateway' => $gateway, 'plain_token' => $plain];
    }
}
