<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskStarted;
use App\Models\Task;

/**
 * Worker start een taak vanaf het veld: status → In uitvoering (+ started_at).
 */
class StartTaskAction
{
    public function handle(Task $task): Task
    {
        if (! $task->canStart()) {
            return $task;
        }

        $task->update([
            'status' => TaskStatus::InProgress,
            'started_at' => $task->started_at ?? now(),
        ]);

        event(new TaskStarted($task->fresh()));

        $task->issue->recalculateStatus();

        return $task->fresh();
    }
}
