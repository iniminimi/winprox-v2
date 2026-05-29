<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookEndpoint;

class StoreWebhookEndpointAction
{
    /**
     * @param  array{url: string, events: list<string>, description?: string|null, is_active?: bool}  $data
     */
    public function handle(array $data, int $tenantId): WebhookEndpoint
    {
        $events = array_values(array_intersect(
            $data['events'] ?? [],
            WebhookEndpoint::AVAILABLE_EVENTS,
        ));

        return WebhookEndpoint::query()->create([
            'tenant_id' => $tenantId,
            'url' => $data['url'],
            'events' => $events,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }
}
