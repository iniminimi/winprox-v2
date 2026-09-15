<?php

namespace App\Actions\Notifications;

use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerNotification;
use InvalidArgumentException;

class CreateNotificationAction
{
    public function handle(
        Tenant $tenant,
        Worker $worker,
        WorkerNotificationType $type,
        string $referenceId,
    ): WorkerNotification {
        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $type->assertReferenceId($referenceId);

        $notification = WorkerNotification::query()->updateOrCreate(
            [
                'worker_id' => $worker->id,
                'type' => $type->value,
                'reference_id' => $referenceId,
            ],
            [
                'tenant_id' => $tenant->id,
                'read_at' => null,
            ],
        );

        $fresh = $notification->fresh();
        if ($fresh === null) {
            throw new InvalidArgumentException('worker_notification_missing');
        }

        return $fresh;
    }
}
