<?php

namespace App\Actions\Tasks;

use App\Models\Task;

class UpdateTaskDetailsAction
{
    public function handle(
        Task $task,
        string $note,
        ?string $scheduledFor,
        int $tenantId,
    ): Task {
        if ((int) $task->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Cannot update task from another tenant');
        }

        $task->update([
            'note' => $note,
            'scheduled_for' => $scheduledFor !== null && $scheduledFor !== '' ? $scheduledFor : null,
        ]);

        return $task->fresh();
    }
}
