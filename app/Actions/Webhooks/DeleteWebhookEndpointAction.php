<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookEndpoint;
use App\Support\Audit\AuditRecorder;

class DeleteWebhookEndpointAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(WebhookEndpoint $endpoint, ?int $actorUserId = null): void
    {
        $tenantId = (int) $endpoint->tenant_id;
        $id = (int) $endpoint->id;
        $url = $endpoint->url;

        $endpoint->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'webhook_endpoint.deleted',
            modelType: WebhookEndpoint::class,
            modelId: $id,
            payload: ['id' => $id, 'url' => $url],
        );
    }
}
