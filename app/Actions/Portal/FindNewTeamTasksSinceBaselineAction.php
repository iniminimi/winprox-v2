<?php

namespace App\Actions\Portal;

use App\Actions\Tasks\TaskBelongsToUnitAction;
use App\Models\Unit;
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
    public function __construct(
        private TaskBelongsToUnitAction $taskBelongsToUnit,
    ) {}

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
        $excludeUnit = $excludeUnitId !== null
            ? Unit::query()->find($excludeUnitId)
            : null;

        return TimePortalData::openTasksForTeam($team)
            ->filter(function ($task) use ($knownIds, $excludeUnit) {
                if (in_array((int) $task->id, $knownIds, true)) {
                    return false;
                }

                if ($excludeUnit !== null && $task->issue !== null
                    && $this->taskBelongsToUnit->handle($task->issue, $excludeUnit)) {
                    return false;
                }

                return true;
            })
            ->values();
    }
}
