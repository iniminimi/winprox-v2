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

        $created = $notification->wasRecentlyCreated;
        $fresh = $notification->fresh();
        if ($fresh === null) {
            throw new InvalidArgumentException('worker_notification_missing');
        }

        $fresh->wasRecentlyCreated = $created;

        return $fresh;
    }

    /**
     * Eén rij per worker×type×referentie. Een bestaande rij wordt niet
     * opnieuw ongelezen gezet en wasRecentlyCreated blijft false.
     */
    public function recordOnce(
        Tenant $tenant,
        Worker $worker,
        WorkerNotificationType $type,
        string $referenceId,
    ): WorkerNotification {
        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $type->assertReferenceId($referenceId);

        return WorkerNotification::query()->firstOrCreate(
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
    }
}
