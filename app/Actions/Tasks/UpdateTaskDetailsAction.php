<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;

class UpdateTaskDetailsAction
{
    public function handle(
        Task $task,
        string $description,
        ?string $scheduledFor,
        int $tenantId,
    ): Task {
        TaskIssueApproval::assertTaskMutable($task);

        if ((int) $task->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Cannot update task from another tenant');
        }

        $task->update([
            'description' => $description,
            'scheduled_for' => $scheduledFor !== null && $scheduledFor !== '' ? $scheduledFor : null,
        ]);

        return $task->fresh();
    }
}
