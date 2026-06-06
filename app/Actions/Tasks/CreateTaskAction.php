<?php

namespace App\Actions\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCreated;
use App\Models\Issue;
use App\Models\Task;
use Carbon\CarbonInterface;

class CreateTaskAction
{
    /**
     * Voegt een taak toe aan een melding, toegewezen aan één team.
     */
    public function handle(
        Issue $issue,
        ?int $internalTeamId = null,
        TaskStatus $status = TaskStatus::New,
        TaskPriority $priority = TaskPriority::Prio3,
        ?string $note = null,
        ?CarbonInterface $startedAt = null,
        bool $recalculateIssue = true,
        bool $dispatchCreated = true,
        array $extra = [],
    ): Task {
        // Prevent task creation for closed issues
        if ($issue->isClosed()) {
            throw new \InvalidArgumentException('Cannot create task for closed issue');
        }

        // Prevent task creation for unapproved issues (except QR source which is always unapproved)
        if (! $issue->isApproved() && $issue->source?->value !== 'qr') {
            throw new \InvalidArgumentException('Cannot create task for unapproved issue');
        }

        $task = $issue->tasks()->create(array_merge([
            'internal_team_id' => $internalTeamId,
            'status' => $status,
            'priority' => $priority,
            'note' => $note,
            'started_at' => $status === TaskStatus::InProgress ? ($startedAt ?? now()) : $startedAt,
        ], $extra));

        if ($dispatchCreated) {
            event(new TaskCreated($task));
        }

        if ($recalculateIssue) {
            $issue->recalculateStatus();
        }

        return $task;
    }
}
