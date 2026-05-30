<?php

namespace App\Actions\Issues;

use App\Actions\Tasks\CreateTaskAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;

/**
 * Stap 2 facility-flow: één taak toewijzen aan een team (status In uitvoering).
 */
class AssignIssueTeamTaskAction
{
    public function __construct(private CreateTaskAction $createTask) {}

    public function handle(Issue $issue, int $internalTeamId, ?string $note = null): Task
    {
        return $this->createTask->handle(
            issue: $issue,
            internalTeamId: $internalTeamId,
            status: TaskStatus::InProgress,
            note: $note,
            startedAt: now(),
        );
    }
}
