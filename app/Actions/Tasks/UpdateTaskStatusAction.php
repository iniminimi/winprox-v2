<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;

class UpdateTaskStatusAction
{
    /**
     * Zet de status van een taak en herberekent meteen de meldingstatus.
     */
    public function handle(Task $task, TaskStatus $status): Task
    {
        $task->update(['status' => $status]);

        $task->issue->recalculateStatus();

        return $task->fresh();
    }
}
