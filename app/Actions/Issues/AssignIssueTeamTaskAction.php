<?php

namespace App\Actions\Issues;

use App\Actions\Tasks\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;

/**
 * Stap 2 facility-flow: één taak toewijzen aan een team (status In uitvoering).
 */
class AssignIssueTeamTaskAction
{
    public function __construct(private CreateTaskAction $createTask) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    public function handle(
        Issue $issue,
        int $internalTeamId,
        ?string $description = null,
        TaskPriority $priority = TaskPriority::Prio3,
        array $extra = [],
    ): Task {
        if ($issue->isInspectionRound()) {
            $dueAt = $issue->recurrence_next_due_at?->copy() ?? now();
            $extra = array_merge([
                'scheduled_for' => $dueAt->toDateString(),
                'due_at' => $dueAt,
                'is_recurring_cycle' => true,
                'recurrence_issue_id' => $issue->id,
                'cycle_number' => 1,
            ], $extra);
        }

        return $this->createTask->handle(
            issue: $issue,
            internalTeamId: $internalTeamId,
            status: TaskStatus::InProgress,
            priority: $priority,
            description: $description,
            startedAt: now(),
            extra: $extra,
        );
    }
}
