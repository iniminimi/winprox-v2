<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Support\Portal\TimePortalData;
use App\Support\Portal\WorkerTaskBaseline;
use Illuminate\Support\Collection;

/**
 * Open, goedgekeurde teamtaken die sinds de Clock Point-baseline nieuw zijn.
 *
 * @return Collection<int, \App\Models\Task>
 */
class FindNewTeamTasksSinceBaselineAction
{
    public function handle(Worker $worker, ?int $excludeUnitId = null): Collection
    {
        $team = $worker->team;
        if ($team === null) {
            return collect();
        }

        $payload = WorkerTaskBaseline::payload((int) $team->id);
        if ($payload === null || $payload['worker_id'] !== (int) $worker->id) {
            return collect();
        }

        $knownIds = $payload['task_ids'];

        return TimePortalData::openTasksForTeam($team)
            ->filter(function ($task) use ($knownIds, $excludeUnitId) {
                if (in_array((int) $task->id, $knownIds, true)) {
                    return false;
                }

                if ($excludeUnitId !== null && (int) ($task->issue?->unit_id) === $excludeUnitId) {
                    return false;
                }

                return true;
            })
            ->values();
    }
}
