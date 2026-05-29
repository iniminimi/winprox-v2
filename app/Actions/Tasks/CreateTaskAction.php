<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCreated;
use App\Models\Issue;
use App\Models\Task;

class CreateTaskAction
{
    /**
     * Voegt een taak toe aan een melding, toegewezen aan één team.
     */
    public function handle(Issue $issue, ?int $internalTeamId = null): Task
    {
        $task = $issue->tasks()->create([
            'internal_team_id' => $internalTeamId,
            'status' => TaskStatus::New,
        ]);

        event(new TaskCreated($task));

        $issue->recalculateStatus();

        return $task;
    }
}
