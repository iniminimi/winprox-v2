<?php

namespace App\Actions\Webhooks;

use App\Jobs\SendWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class TestWebhookEndpointAction
{
    public function handle(WebhookEndpoint $endpoint, int $tenantId): WebhookDelivery
    {
        if ((int) $endpoint->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Webhook endpoint does not belong to the specified tenant');
        }

        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $tenantId,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'test',
            'payload' => [
                'version' => '1.0',
                'event' => 'test',
                'timestamp' => now()->toIso8601String(),
                'message' => 'Test webhook from WinProx',
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        SendWebhookDeliveryJob::dispatch($delivery->id);

        return $delivery;
    }
}
