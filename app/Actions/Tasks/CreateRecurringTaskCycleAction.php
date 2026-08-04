<?php

namespace App\Actions\Tasks;

use App\Actions\Communication\EnsureTaskTranslationSlotsAction;
use App\Actions\Issues\AddIssueUpdateAction;
use App\Actions\Issues\RecalculateIssueStatusAction;
use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCreated;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Recurrence\RecurrenceSchedule;
use App\Support\Translation\LocaleSupport;
use Carbon\Carbon;

/**
 * Opent een terugkerende taakcyclus wanneer de planning dit vereist.
 */
class CreateRecurringTaskCycleAction
{
    public function __construct(
        private RecalculateIssueStatusAction $recalculateIssueStatus,
        private EnsureTaskTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    public function handle(Issue $issue, ?Carbon $now = null): ?Task
    {
        if (! $issue->is_recurring || ! $issue->recurrence_active || $issue->recurrence_paused_at !== null) {
            return null;
        }

        if ($issue->status === TaskStatus::Closed) {
            return null;
        }

        $nextDueAt = $issue->recurrence_next_due_at?->copy();
        if ($nextDueAt === null) {
            return null;
        }

        $now ??= now();
        $leadDays = max(1, (int) ($issue->recurrence_lead_days ?? 30));
        $openAt = $nextDueAt->copy()->subDays($leadDays);

        if ($openAt->gt($now)) {
            return null;
        }

        $existing = Task::query()
            ->where('recurrence_issue_id', $issue->id)
            ->whereDate('due_at', $nextDueAt->toDateString())
            ->exists();

        if ($existing) {
            return null;
        }

        // Geen nieuwe cyclus zolang er nog een open taak van deze reeks loopt —
        // voorkomt stapeling van onafgehandelde recurring-taken.
        $hasOpenCycle = Task::query()
            ->where('recurrence_issue_id', $issue->id)
            ->whereIn('status', TaskStatus::openValues())
            ->exists();

        if ($hasOpenCycle) {
            return null;
        }

        $latestCycle = Task::query()
            ->where('recurrence_issue_id', $issue->id)
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->first();

        $teamId = $latestCycle?->internal_team_id
            ?? $issue->tasks()->whereNotNull('internal_team_id')->value('internal_team_id');

        $cycleNumber = (int) ($latestCycle?->cycle_number ?? 0) + 1;

        $task = $issue->tasks()->create([
            'internal_team_id' => $teamId,
            'status' => TaskStatus::New,
            'description' => $issue->description,
            'original_language' => LocaleSupport::normalize($issue->original_language),
            'scheduled_for' => $nextDueAt->toDateString(),
            'due_at' => $nextDueAt,
            'is_recurring_cycle' => true,
            'recurrence_issue_id' => $issue->id,
            'cycle_number' => $cycleNumber,
            'carryover_from_task_id' => $latestCycle?->id,
        ]);

        $this->ensureTranslationSlots->handle($task->fresh());

        event(new TaskCreated($task));

        $intervalUnit = $issue->recurrence_interval_unit instanceof RecurrenceIntervalUnit
            ? $issue->recurrence_interval_unit
            : RecurrenceIntervalUnit::tryFrom((string) $issue->recurrence_interval_unit) ?? RecurrenceIntervalUnit::Year;

        $followingDueAt = RecurrenceSchedule::nextDueAt(
            $nextDueAt,
            (int) ($issue->recurrence_interval_value ?? 1),
            $intervalUnit,
        );

        $issue->update([
            'recurrence_next_due_at' => $followingDueAt,
            'recurrence_last_task_created_at' => $now,
        ]);

        $this->recalculateIssueStatus->handle($issue);

        return $task->fresh();
    }
}
