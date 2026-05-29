<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'url' => 'https://hooks.test/'.fake()->uuid(),
            'events' => ['issue.created'],
            'description' => null,
            'is_active' => true,
        ];
    }
}
