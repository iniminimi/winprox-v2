<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;

/**
 * Centrale audit-registratie voor domein-mutaties (via listener op events).
 */
class LogAuditAction
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(
        ?int $userId,
        ?int $tenantId,
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $payload = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
