<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;

class NotifyWorkersRosterPublishedAction
{
    public function __construct(private CreateNotificationAction $createNotification) {}

    /**
     * @param  list<int>  $workerIds
     */
    public function handle(Tenant $tenant, array $workerIds, string $periodStart): int
    {
        $ids = array_values(array_unique(array_map('intval', $workerIds)));
        if ($ids === []) {
            return 0;
        }

        WorkerNotificationType::RosterPublished->assertReferenceId($periodStart);

        $workers = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->get();

        $count = 0;
        foreach ($workers as $worker) {
            $this->createNotification->handle(
                $tenant,
                $worker,
                WorkerNotificationType::RosterPublished,
                $periodStart,
            );
            $count++;
        }

        return $count;
    }
}
