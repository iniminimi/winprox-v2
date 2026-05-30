<?php

namespace App\Actions\Tasks;

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
        ?string $note = null,
        ?CarbonInterface $startedAt = null,
        bool $recalculateIssue = true,
        bool $dispatchCreated = true,
        array $extra = [],
    ): Task {
        $task = $issue->tasks()->create(array_merge([
            'internal_team_id' => $internalTeamId,
            'status' => $status,
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
