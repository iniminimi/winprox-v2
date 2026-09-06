<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class AcknowledgeTimeRosterViewAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, int $tenantId): void
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId || ! $worker->is_active) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $viewedAt = now();

        $this->audit->record(
            userId: $worker->user_id !== null ? (int) $worker->user_id : null,
            tenantId: $tenantId,
            action: 'time.roster.viewed',
            modelType: Tenant::class,
            modelId: $tenantId,
            payload: [
                'worker_id' => (int) $worker->id,
                'first_name' => (string) $worker->first_name,
                'last_name' => (string) $worker->last_name,
                'viewed_at' => $viewedAt->toIso8601String(),
            ],
        );
    }
}
