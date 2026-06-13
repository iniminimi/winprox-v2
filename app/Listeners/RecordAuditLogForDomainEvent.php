<?php

namespace App\Listeners;

use App\Actions\Audit\LogAuditAction;
use App\Contracts\WebhookEvent;
use App\Models\Issue;
use App\Models\Task;

/**
 * Schrijft audit_logs voor elk domein-event dat WebhookEvent implementeert.
 */
class RecordAuditLogForDomainEvent
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(WebhookEvent $event): void
    {
        if (! config('audit.enabled', true)) {
            return;
        }

        $tenantId = $event->webhookTenantId();

        // Skip audit logging for orphaned events without valid tenant (e.g., IMAP messages)
        if ($tenantId === null || $tenantId <= 0) {
            return;
        }

        $payload = $event->webhookPayload();
        [$modelType, $modelId] = $this->resolveModel($event->webhookEventName(), $payload);

        $this->logAudit->handle(
            userId: $this->resolveUserId($payload),
            tenantId: $tenantId,
            action: $event->webhookEventName(),
            modelType: $modelType,
            modelId: $modelId,
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveModel(string $eventName, array $payload): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        if ($id === null || $id <= 0) {
            return [null, null];
        }

        return match (true) {
            str_starts_with($eventName, 'issue.') => [Issue::class, $id],
            str_starts_with($eventName, 'task.') => [Task::class, $id],
            str_starts_with($eventName, 'unit.') => [\App\Models\Unit::class, (int) ($payload['unit_id'] ?? $id)],
            str_starts_with($eventName, 'location.') => [\App\Models\Location::class, $id],
            str_starts_with($eventName, 'user.') => [\App\Models\User::class, $payload['id'] ?? $id],
            str_starts_with($eventName, 'tenant.') => [\App\Models\Tenant::class, $id],
            str_starts_with($eventName, 'subscription.') => [\App\Models\Tenant::class, $id],
            str_starts_with($eventName, 'webhook_endpoint.') => [\App\Models\WebhookEndpoint::class, $id],
            default => [null, $id],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveUserId(array $payload): ?int
    {
        foreach (['approved_by', 'user_id', 'actor_user_id'] as $key) {
            if (isset($payload[$key]) && (int) $payload[$key] > 0) {
                return (int) $payload[$key];
            }
        }

        return null;
    }
}
