<?php

namespace App\Actions\Tasks;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;

class UpdateTaskPriorityAction
{
    /**
     * Wijzigt de prioriteit van een taak.
     *
     * @param  int  $tenantId  De tenant ID voor scoping
     * @param  int|null  $actorUserId  De ID van de gebruiker die de wijziging uitvoert (voor audit/logging)
     */
    public function handle(
        Task $task,
        TaskPriority $priority,
        int $tenantId,
        ?int $actorUserId = null,
    ): Task {
        if ((int) $task->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Cannot update task from another tenant');
        }

        TaskIssueApproval::assertTaskMutable($task);

        $task->update(['priority' => $priority]);

        return $task->fresh();
    }
}
