<?php

namespace App\Actions\Tasks;

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\DispatchWorkerPushAction;
use App\Enums\WorkerNotificationType;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use Illuminate\Support\Facades\Cache;

/**
 * Push bij een nieuwe, zichtbare taak: persoonlijk toegewezen → die uitvoerder (één keer per taak);
 * teamtaak zonder persoon → alle actieve uitvoerders van het team, max. 1 push per kwartier.
 * Wederkerende cycli krijgen één ochtendpush (NotifyWorkersOfRecurringTasksAction).
 */
class NotifyWorkersOfNewTaskAction
{
    public const TEAM_BUNDLE_SECONDS = 900;

    public function __construct(
        private CreateNotificationAction $createNotification,
        private DispatchWorkerPushAction $dispatchPush,
    ) {}

    public function handle(Task $task): void
    {
        if ($task->is_recurring_cycle || ! $task->isOpen() || $task->internal_team_id === null) {
            return;
        }

        $task->loadMissing('issue');
        if ($task->issue === null || ! $task->issue->isApproved()) {
            return;
        }

        $tenant = Tenant::query()->find($task->tenant_id);
        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($task->assigned_worker_id !== null) {
            $this->notifyAssigned($tenant, $task);

            return;
        }

        Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('internal_team_id', $task->internal_team_id)
            ->where('is_active', true)
            ->get()
            ->each(function (Worker $worker) use ($tenant): void {
                if (! Cache::add('worker_push:team_task:'.$worker->id, true, self::TEAM_BUNDLE_SECONDS)) {
                    return;
                }

                $this->dispatchPush->handle(
                    $tenant,
                    $worker,
                    'time.portal.push.team_task_title',
                    'time.portal.push.team_task_body',
                    [],
                    '',
                    'team_task',
                );
            });
    }

    private function notifyAssigned(Tenant $tenant, Task $task): void
    {
        $worker = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($task->assigned_worker_id)
            ->first();

        if (! $worker instanceof Worker) {
            return;
        }

        $row = $this->createNotification->recordOnce($tenant, $worker, WorkerNotificationType::TaskAssigned, (string) $task->id);
        if (! $row->wasRecentlyCreated) {
            return;
        }

        $this->dispatchPush->handle(
            $tenant,
            $worker,
            'time.portal.push.task_assigned_title',
            'time.portal.push.task_assigned_body',
            [],
            '',
            'task_assigned:'.$task->id,
        );
    }
}
