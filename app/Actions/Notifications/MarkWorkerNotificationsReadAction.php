<?php

namespace App\Actions\Notifications;

use App\Enums\WorkerNotificationType;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class MarkWorkerNotificationsReadAction
{
    public function handle(
        Worker $worker,
        int $tenantId,
        WorkerNotificationType $type,
        ?string $referenceId = null,
    ): int {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if ($referenceId !== null) {
            $type->assertReferenceId($referenceId);
        }

        return WorkerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('type', $type->value)
            ->whereNull('read_at')
            ->when($referenceId !== null, fn ($q) => $q->where('reference_id', $referenceId))
            ->update(['read_at' => now()]);
    }
}
