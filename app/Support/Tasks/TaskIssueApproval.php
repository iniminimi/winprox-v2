<?php

namespace App\Support\Tasks;

use App\Models\Issue;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

final class TaskIssueApproval
{
    public static function assertIssueApproved(Issue $issue): void
    {
        if ($issue->isApproved()) {
            return;
        }

        throw ValidationException::withMessages([
            'issue' => [__('tasks.errors.issue_not_approved')],
        ]);
    }

    public static function assertTaskMutable(Task $task): void
    {
        $task->loadMissing('issue');
        self::assertIssueApproved($task->issue);
    }
}
