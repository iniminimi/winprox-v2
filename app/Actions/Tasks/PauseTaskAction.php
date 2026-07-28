<?php

namespace App\Actions\Tasks;

use App\Actions\Issues\AddIssueUpdateAction;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskIssueApproval;
use App\Support\Tasks\TaskStatusTransitions;
use Illuminate\Validation\ValidationException;

class PauseTaskAction
{
    public function __construct(private AddIssueUpdateAction $addUpdate) {}

    public function handle(Task $task, string $note, ?User $actor = null): Task
    {
        TaskIssueApproval::assertTaskMutable($task);

        if ($task->status !== TaskStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => [__('tasks.errors.pause_only_in_progress')],
            ]);
        }

        $this->addUpdate->handle(
            issue: $task->issue,
            description: trim($note),
            userId: $actor?->id,
            kind: 'pause',
            taskId: (int) $task->id,
        );

        return $task->fresh();
    }
}
