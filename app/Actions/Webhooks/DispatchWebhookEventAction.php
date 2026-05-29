<?php

namespace App\Actions\Webhooks;

use App\Jobs\SendWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Zoekt actieve endpoints voor een tenant+event en plant levering (queued).
 */
class DispatchWebhookEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, array $payload, int $tenantId): void
    {
        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'tenant_id' => $tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'status' => WebhookDelivery::STATUS_PENDING,
                'attempts' => 0,
            ]);

            SendWebhookDeliveryJob::dispatch($delivery->id);
        }
    }
}
