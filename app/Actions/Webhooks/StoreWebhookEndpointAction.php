<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookEndpoint;
use App\Support\Audit\AuditRecorder;

class StoreWebhookEndpointAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{url: string, events: list<string>, description?: string|null, is_active?: bool}  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): WebhookEndpoint
    {
        $events = array_values(array_intersect(
            $data['events'] ?? [],
            WebhookEndpoint::AVAILABLE_EVENTS,
        ));

        $endpoint = WebhookEndpoint::query()->create([
            'tenant_id' => $tenantId,
            'url' => $data['url'],
            'events' => $events,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'webhook_endpoint.created',
            modelType: WebhookEndpoint::class,
            modelId: (int) $endpoint->id,
            payload: ['id' => $endpoint->id, 'url' => $endpoint->url, 'events' => $events],
        );

        return $endpoint;
    }
}
