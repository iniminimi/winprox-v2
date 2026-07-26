<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Support\Portal\WorkerTaskBaseline;

/**
 * Voegt taak-id's toe aan de baseline (na dismiss of bekijken).
 */
class MarkTeamTasksSeenInBaselineAction
{
    /**
     * @param  list<int>  $taskIds
     */
    public function handle(Worker $worker, array $taskIds): void
    {
        $team = $worker->team;
        if ($team === null || $taskIds === []) {
            return;
        }

        $payload = WorkerTaskBaseline::payload((int) $team->id);
        if ($payload === null || $payload['worker_id'] !== (int) $worker->id) {
            return;
        }

        WorkerTaskBaseline::store(
            (int) $team->id,
            (int) $worker->id,
            array_merge($payload['task_ids'], $taskIds),
        );
    }
}
