<?php

namespace App\Actions\Tasks;

use App\Actions\Notifications\DispatchWorkerPushAction;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;

/**
 * Eén push per uitvoerder na de dagelijkse recurrence-tick: "Er staan :count nieuwe taken voor je klaar."
 * Toegewezen cyclus → die uitvoerder; anders alle actieve uitvoerders van het team.
 */
class NotifyWorkersOfRecurringTasksAction
{
    public function __construct(private DispatchWorkerPushAction $dispatchPush) {}

    /**
     * @param  list<Task>  $tasks
     */
    public function handle(Tenant $tenant, array $tasks): void
    {
        $counts = [];

        foreach ($tasks as $task) {
            if ((int) $task->tenant_id !== (int) $tenant->id || $task->internal_team_id === null) {
                continue;
            }

            $workerIds = $task->assigned_worker_id !== null
                ? [(int) $task->assigned_worker_id]
                : Worker::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('internal_team_id', $task->internal_team_id)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

            foreach ($workerIds as $workerId) {
                $counts[$workerId] = ($counts[$workerId] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return;
        }

        $date = now()->toDateString();

        Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_keys($counts))
            ->get()
            ->each(fn (Worker $worker) => $this->dispatchPush->handle(
                $tenant,
                $worker,
                'time.portal.push.recurring_tasks_title',
                'time.portal.push.recurring_tasks_body',
                ['count' => (string) $counts[(int) $worker->id]],
                '',
                'recurring_tasks:'.$date,
            ));
    }
}
