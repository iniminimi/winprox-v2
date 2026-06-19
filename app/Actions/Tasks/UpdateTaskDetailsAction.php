<?php

namespace App\Actions\Tasks;

use App\Actions\Communication\EnsureTaskTranslationSlotsAction;
use App\Actions\Communication\InvalidateTaskTranslationsOnSourceChangeAction;
use App\Models\Task;
use App\Support\Tasks\TaskIssueApproval;

class UpdateTaskDetailsAction
{
    public function __construct(
        private InvalidateTaskTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureTaskTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    public function handle(
        Task $task,
        string $description,
        ?string $scheduledFor,
        int $tenantId,
        ?int $actorUserId = null,
    ): Task {
        TaskIssueApproval::assertTaskMutable($task);

        if ((int) $task->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Cannot update task from another tenant');
        }

        $previousDescription = $task->description;

        $task->update([
            'description' => $description,
            'scheduled_for' => $scheduledFor !== null && $scheduledFor !== '' ? $scheduledFor : null,
        ]);

        $fresh = $task->fresh();
        $this->invalidateTranslations->handle($fresh, $previousDescription, $actorUserId);
        $this->ensureTranslationSlots->handle($fresh);

        return $fresh;
    }
}
