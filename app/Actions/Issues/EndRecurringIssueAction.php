<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Webhook\IssueStatusWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sluit een terugkerende melding definitief: geen nieuwe cycli, status Gesloten.
 */
class EndRecurringIssueAction
{
    public function __construct(
        private AddIssueUpdateAction $addUpdate,
        private AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        Issue $issue,
        User $actor,
        string $reason,
    ): Issue {
        if (! $issue->is_recurring) {
            throw ValidationException::withMessages([
                'status' => [__('issues.errors.cannot_end_not_recurring')],
            ]);
        }

        if (! $issue->recurrence_active) {
            throw ValidationException::withMessages([
                'status' => [__('issues.errors.cannot_end_already_ended')],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'endReason' => [__('issues.errors.end_recurring_reason_required')],
            ]);
        }

        return DB::transaction(function () use ($issue, $actor, $reason) {
            $this->addUpdate->handle(
                issue: $issue,
                description: $reason,
                userId: $actor->id,
                kind: 'close_reason',
            );

            $openTasks = $issue->tasks()
                ->whereIn('status', [TaskStatus::New->value, TaskStatus::InProgress->value])
                ->get();

            foreach ($openTasks as $task) {
                $task->update([
                    'status' => TaskStatus::Closed,
                    'completed_at' => $task->completed_at ?? now(),
                ]);
            }

            $before = $issue->status instanceof TaskStatus
                ? $issue->status
                : (TaskStatus::tryFrom((string) $issue->status) ?? TaskStatus::New);

            $issue->update([
                'recurrence_active' => false,
                'status' => TaskStatus::Closed,
            ]);

            $fresh = $issue->fresh(['tasks']);
            IssueStatusWebhook::dispatchIfChanged($fresh, $before);

            $this->auditRecorder->record(
                userId: $actor->id,
                tenantId: (int) $issue->tenant_id,
                action: 'issue.recurrence_ended',
                modelType: Issue::class,
                modelId: (int) $issue->id,
                payload: [
                    'id' => $issue->id,
                    'reason' => $reason,
                    'open_tasks_closed' => $openTasks->count(),
                ],
            );

            return $fresh;
        });
    }
}
