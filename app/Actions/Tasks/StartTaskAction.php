<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskStarted;
use App\Models\Task;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

/**
 * Worker start een taak vanaf het veld: status → In uitvoering (+ started_at).
 */
class StartTaskAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Task $task, ?Worker $worker = null, ?\Carbon\Carbon $clientTimestamp = null): Task
    {
        if (! $task->canStart()) {
            return $task;
        }

        $task->update([
            'status' => TaskStatus::InProgress,
            'started_at' => $task->started_at ?? ($clientTimestamp ?? now()),
        ]);

        $this->audit->record(
            userId: null, // Workers are not users
            tenantId: $task->tenant_id,
            action: 'task_started',
            modelType: Task::class,
            modelId: $task->id,
            payload: [
                'task_id' => $task->id,
                'worker_id' => $worker?->id,
                'client_timestamp' => $clientTimestamp?->toIso8601String(),
            ],
        );

        event(new TaskStarted($task->fresh()));

        $task->issue->recalculateStatus();

        return $task->fresh();
    }
}
