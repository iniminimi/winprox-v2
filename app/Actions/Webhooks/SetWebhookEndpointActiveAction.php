<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookEndpoint;
use App\Support\Audit\AuditRecorder;

class SetWebhookEndpointActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(WebhookEndpoint $endpoint, bool $active, ?int $actorUserId = null): WebhookEndpoint
    {
        $endpoint->update(['is_active' => $active]);

        $fresh = $endpoint->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'webhook_endpoint.updated',
            modelType: WebhookEndpoint::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
