<?php

namespace App\Actions\Tasks;

use App\Actions\Issues\RecalculateIssueStatusAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCreated;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CreateTaskAction
{
    public function __construct(private RecalculateIssueStatusAction $recalculateIssueStatus) {}

    /**
     * Voegt een taak toe aan een melding, toegewezen aan één team.
     */
    public function handle(
        Issue $issue,
        ?int $internalTeamId = null,
        TaskStatus $status = TaskStatus::New,
        TaskPriority $priority = TaskPriority::Prio3,
        ?string $description = null,
        ?CarbonInterface $startedAt = null,
        bool $recalculateIssue = true,
        bool $dispatchCreated = true,
        array $extra = [],
        bool $duringIssueIntake = false,
    ): Task {
        // Prevent task creation for closed issues
        if ($issue->isClosed()) {
            throw new \InvalidArgumentException('Cannot create task for closed issue');
        }

        if (! $issue->isApproved() && ! $duringIssueIntake) {
            throw ValidationException::withMessages([
                'issue' => [__('tasks.errors.issue_not_approved')],
            ]);
        }

        $task = $issue->tasks()->create(array_merge([
            'internal_team_id' => $internalTeamId,
            'status' => $status,
            'priority' => $priority,
            'description' => $description,
            'started_at' => $status === TaskStatus::InProgress ? ($startedAt ?? now()) : $startedAt,
        ], $extra));

        if ($dispatchCreated) {
            event(new TaskCreated($task));
        }

        if ($recalculateIssue) {
            $this->recalculateIssueStatus->handle($issue);
        }

        return $task;
    }
}
