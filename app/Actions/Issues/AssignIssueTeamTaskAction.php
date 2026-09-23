<?php

namespace App\Actions\Issues;

use App\Actions\Tasks\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Recurrence\RecurrenceSchedule;
use Carbon\Carbon;

/**
 * Stap 2 facility-flow: één taak toewijzen aan een team (status In uitvoering),
 * optioneel aan één worker van dat team.
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
        ?int $assignedWorkerId = null,
    ): Task {
        $openedDueAt = null;
        if ($issue->isInspectionRound()) {
            $openedDueAt = $issue->recurrence_next_due_at?->copy() ?? now();
            $extra = array_merge([
                'scheduled_for' => $openedDueAt->toDateString(),
                'due_at' => $openedDueAt,
                'is_recurring_cycle' => (bool) $issue->is_recurring,
                'recurrence_issue_id' => $issue->is_recurring ? $issue->id : null,
                'cycle_number' => $issue->is_recurring ? 1 : null,
            ], $extra);
        }

        $task = $this->createTask->handle(
            issue: $issue,
            internalTeamId: $internalTeamId,
            status: TaskStatus::InProgress,
            priority: $priority,
            description: $description,
            startedAt: now(),
            extra: $extra,
            assignedWorkerId: $assignedWorkerId,
        );

        if ($openedDueAt instanceof Carbon && $issue->is_recurring) {
            $fresh = $issue->fresh();
            if ($fresh !== null) {
                $current = $fresh->recurrence_next_due_at;
                if ($current === null || $current->toDateString() === $openedDueAt->toDateString()) {
                    $fresh->update([
                        'recurrence_next_due_at' => RecurrenceSchedule::followingDueAtForIssue($fresh, $openedDueAt),
                        'recurrence_last_task_created_at' => now(),
                    ]);
                }
            }
        }

        return $task;
    }
}
