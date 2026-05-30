<?php

namespace App\Actions\Tasks;

use App\Actions\Issues\AddIssueUpdateAction;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskStatusTransitions;
use Illuminate\Validation\ValidationException;

class UpdateTaskStatusAction
{
    public function __construct(private AddIssueUpdateAction $addUpdate) {}

    public function handle(
        Task $task,
        TaskStatus $status,
        ?User $actor = null,
        ?string $reason = null,
    ): Task {
        $from = $task->status instanceof TaskStatus
            ? $task->status
            : (TaskStatus::tryFrom((string) $task->status) ?? TaskStatus::New);

        if (! TaskStatusTransitions::allows($from, $status)) {
            throw ValidationException::withMessages([
                'status' => [__('tasks.errors.invalid_transition')],
            ]);
        }

        if (TaskStatusTransitions::requiresReason($from, $status)) {
            if ($reason === null || trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => [__('tasks.errors.reason_required')],
                ]);
            }

            $this->addUpdate->handle(
                issue: $task->issue,
                body: trim($reason),
                userId: $actor?->id,
                kind: 'status_reason',
            );
        }

        $updates = ['status' => $status];

        if ($status === TaskStatus::InProgress && $task->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($status === TaskStatus::Done && $task->completed_at === null) {
            $updates['completed_at'] = now();
            $updates['started_at'] = $task->started_at ?? now();
        }

        if ($status === TaskStatus::Closed) {
            $updates['completed_at'] = $task->completed_at ?? now();
        }

        if ($status === TaskStatus::New && $from === TaskStatus::Closed) {
            $updates['started_at'] = null;
            $updates['completed_at'] = null;
        }

        $task->update($updates);

        $task->issue->recalculateStatus();

        return $task->fresh();
    }
}
