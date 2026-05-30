<?php

namespace App\Support\Audit;

use App\Actions\Audit\LogAuditAction;

/**
 * Centrale helper voor audit_logs buiten WebhookEvent-domein-events.
 */
final class AuditRecorder
{
    public function __construct(private LogAuditAction $logAudit) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        ?int $userId,
        int $tenantId,
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        array $payload = [],
    ): void {
        if (! config('audit.enabled', true)) {
            return;
        }

        $this->logAudit->handle(
            userId: $userId,
            tenantId: $tenantId,
            action: $action,
            modelType: $modelType,
            modelId: $modelId,
            payload: $payload,
        );
    }
}
