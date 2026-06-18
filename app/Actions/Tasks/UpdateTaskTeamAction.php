<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;

class UpdateTaskTeamAction
{
    public function handle(Task $task, int $internalTeamId): Task
    {
        TaskIssueApproval::assertTaskMutable($task);
        if ((int) $task->internal_team_id === $internalTeamId) {
            return $task;
        }

        $task->update(['internal_team_id' => $internalTeamId]);

        return $task->fresh(['issue.location', 'issue.unit', 'team']);
    }
}
