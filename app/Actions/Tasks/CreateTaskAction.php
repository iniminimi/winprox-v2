<?php

namespace App\Actions\Tasks;

use App\Actions\Communication\EnsureTaskTranslationSlotsAction;
use App\Actions\Issues\RecalculateIssueStatusAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCreated;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;
use App\Support\Translation\LocaleSupport;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CreateTaskAction
{
    public function __construct(
        private RecalculateIssueStatusAction $recalculateIssueStatus,
        private EnsureTaskTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * Voegt een taak toe aan een melding, toegewezen aan één team (optioneel één worker).
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
        ?int $assignedWorkerId = null,
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

        if ($assignedWorkerId !== null) {
            if ($internalTeamId === null) {
                throw ValidationException::withMessages([
                    'assigned_worker_id' => [__('tasks.errors.worker_not_on_team')],
                ]);
            }

            $ok = \App\Models\Worker::query()
                ->where('tenant_id', $issue->tenant_id)
                ->where('id', $assignedWorkerId)
                ->where('internal_team_id', $internalTeamId)
                ->where('is_active', true)
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages([
                    'assigned_worker_id' => [__('tasks.errors.worker_not_on_team')],
                ]);
            }
        }

        $payload = array_merge([
            'internal_team_id' => $internalTeamId,
            'assigned_worker_id' => $assignedWorkerId,
            'status' => $status,
            'priority' => $priority,
            'description' => $description,
            'started_at' => $status === TaskStatus::InProgress ? ($startedAt ?? now()) : $startedAt,
            'original_language' => LocaleSupport::normalize($issue->original_language),
        ], $extra);

        $task = $issue->tasks()->create($payload);

        if ($dispatchCreated) {
            event(new TaskCreated($task));
        }

        $fresh = $task->fresh();
        $this->ensureTranslationSlots->handle($fresh);

        if ($recalculateIssue) {
            $this->recalculateIssueStatus->handle($issue);
        }

        return $fresh;
    }
}
