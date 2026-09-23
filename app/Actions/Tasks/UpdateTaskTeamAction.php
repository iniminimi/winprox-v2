<?php

namespace App\Actions\Tasks;

use App\Models\Task;

/**
 * @deprecated Prefer UpdateTaskAssignmentAction (team + optionele worker).
 */
class UpdateTaskTeamAction
{
    public function __construct(private UpdateTaskAssignmentAction $updateAssignment) {}

    public function handle(Task $task, int $internalTeamId): Task
    {
        return $this->updateAssignment->handle($task, $internalTeamId, null);
    }
}
