<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\WorkerNotificationItem;
use App\Enums\WorkerNotificationType;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class ListWorkerNotificationsAction
{
    /**
     * @param  WorkerNotificationType|list<WorkerNotificationType>|null  $type
     * @return list<WorkerNotificationItem>
     */
    public function handle(
        Worker $worker,
        int $tenantId,
        WorkerNotificationType|array|null $type = null,
        bool $unreadOnly = false,
    ): array {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $types = $type === null ? null : array_map(
            fn (WorkerNotificationType $t) => $t->value,
            is_array($type) ? $type : [$type],
        );

        $rows = WorkerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->when($types !== null, fn ($q) => $q->whereIn('type', $types))
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return $rows->map(function (WorkerNotification $notification) {
            $type = $notification->type;

            return new WorkerNotificationItem(
                id: (int) $notification->id,
                type: $type,
                referenceId: $notification->reference_id,
                readAt: $notification->read_at,
                updatedAt: $notification->updated_at,
                target: $type->portalTarget($notification->reference_id),
            );
        })->all();
    }
}
