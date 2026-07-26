<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Support\Portal\TimePortalData;
use App\Support\Portal\WorkerTaskBaseline;

/**
 * Zet/vernieuwt de baseline op alle huidige open teamtaken (Clock Point-weergave = gezien).
 */
class SyncWorkerOpenTaskBaselineAction
{
    public function handle(Worker $worker): void
    {
        $team = $worker->team;
        if ($team === null) {
            return;
        }

        $taskIds = TimePortalData::openTasksForTeam($team)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        WorkerTaskBaseline::store((int) $team->id, (int) $worker->id, $taskIds);
    }
}
