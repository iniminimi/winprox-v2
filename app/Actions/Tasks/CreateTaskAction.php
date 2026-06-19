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

        $payload = array_merge([
            'internal_team_id' => $internalTeamId,
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
