<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\DispatchWorkerPushAction;
use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Push\WorkerPushScreen;
use Carbon\Carbon;

class NotifyWorkersRosterPublishedAction
{
    public function __construct(
        private CreateNotificationAction $createNotification,
        private DispatchWorkerPushAction $dispatchPush,
    ) {}

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
            $row = $this->createNotification->handle(
                $tenant,
                $worker,
                WorkerNotificationType::RosterPublished,
                $periodStart,
            );
            if ($row->wasRecentlyCreated) {
                $this->dispatchPush->handle(
                    $tenant,
                    $worker,
                    'time.portal.push.roster_published_title',
                    'time.portal.push.roster_published_body',
                    ['date' => Carbon::parse($periodStart)->format('d-m-Y')],
                    WorkerPushScreen::SCHEDULE,
                    'roster_published:'.$periodStart,
                );
            }
            $count++;
        }

        return $count;
    }
}
