<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\Worker;
use App\Support\Tasks\TaskIssueApproval;
use Illuminate\Validation\ValidationException;

class UpdateTaskAssignmentAction
{
    public function handle(Task $task, int $internalTeamId, ?int $assignedWorkerId = null): Task
    {
        TaskIssueApproval::assertTaskMutable($task);

        $assignedWorkerId = $this->normalizeWorkerId(
            $assignedWorkerId,
            $internalTeamId,
            (int) $task->tenant_id,
        );

        if ((int) $task->internal_team_id === $internalTeamId
            && (int) ($task->assigned_worker_id ?? 0) === (int) ($assignedWorkerId ?? 0)) {
            return $task;
        }

        $task->update([
            'internal_team_id' => $internalTeamId,
            'assigned_worker_id' => $assignedWorkerId,
        ]);

        return $task->fresh(['issue.location', 'issue.unit', 'team', 'assignedWorker']);
    }

    private function normalizeWorkerId(?int $assignedWorkerId, int $teamId, int $tenantId): ?int
    {
        if ($assignedWorkerId === null) {
            return null;
        }

        $ok = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $assignedWorkerId)
            ->where('internal_team_id', $teamId)
            ->where('is_active', true)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'assigned_worker_id' => [__('tasks.errors.worker_not_on_team')],
            ]);
        }

        return $assignedWorkerId;
    }
}
