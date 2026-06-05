<?php

namespace App\Actions\Webhooks;

use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Support\Audit\AuditRecorder;
use App\Support\Tenancy;

class StoreWebhookEndpointAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{url: string, events: list<string>, description?: string|null, is_active?: bool}  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): WebhookEndpoint
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        
        if (! $tenant->hasApiAccess()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('API-toegang en webhooks zijn niet beschikbaar tijdens de proefperiode.');
        }

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
