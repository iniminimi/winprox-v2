<?php

namespace App\Actions\Notifications;

use App\Enums\WorkerNotificationType;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class MarkWorkerNotificationsReadAction
{
    /**
     * @param  WorkerNotificationType|list<WorkerNotificationType>  $type
     */
    public function handle(
        Worker $worker,
        int $tenantId,
        WorkerNotificationType|array $type,
        ?string $referenceId = null,
    ): int {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $types = is_array($type) ? $type : [$type];

        if ($referenceId !== null) {
            foreach ($types as $single) {
                $single->assertReferenceId($referenceId);
            }
        }

        return WorkerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->whereIn('type', array_map(fn (WorkerNotificationType $t) => $t->value, $types))
            ->whereNull('read_at')
            ->when($referenceId !== null, fn ($q) => $q->where('reference_id', $referenceId))
            ->update(['read_at' => now()]);
    }
}
