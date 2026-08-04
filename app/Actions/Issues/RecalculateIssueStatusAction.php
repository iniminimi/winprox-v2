<?php

namespace App\Actions\Issues;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Support\Webhook\IssueStatusWebhook;

class RecalculateIssueStatusAction
{
    /**
     * Leidt de meldingstatus af uit de onderliggende taken en slaat ze op.
     * Rollup-regels: zie WINPROX_RULES.md §4.2.
     */
    public function handle(Issue $issue): TaskStatus
    {
        // Gesloten terugkerende reeks (!recurrence_active) blijft Gesloten ondanks historische Done-cycli.
        if ($issue->is_recurring && ! $issue->recurrence_active) {
            $derived = TaskStatus::Closed;
            if ($issue->status !== $derived) {
                $before = $issue->status instanceof TaskStatus
                    ? $issue->status
                    : (TaskStatus::tryFrom((string) $issue->status) ?? TaskStatus::New);
                $issue->status = $derived;
                $issue->save();
                IssueStatusWebhook::dispatchIfChanged($issue, $before);
            }

            return $derived;
        }

        $statuses = $issue->tasks()
            ->pluck('status')
            ->map(fn ($status) => $status instanceof TaskStatus ? $status->value : $status);

        $derived = match (true) {
            $statuses->isEmpty() => TaskStatus::New,
            $statuses->every(fn ($s) => $s === TaskStatus::Closed->value) => TaskStatus::Closed,
            $statuses->every(fn ($s) => in_array($s, [TaskStatus::Done->value, TaskStatus::Closed->value], true)) => TaskStatus::Done,
            $statuses->contains(TaskStatus::InProgress->value) => TaskStatus::InProgress,
            default => TaskStatus::New,
        };

        if ($issue->status !== $derived) {
            $before = $issue->status instanceof TaskStatus
                ? $issue->status
                : (TaskStatus::tryFrom((string) $issue->status) ?? TaskStatus::New);
            $issue->status = $derived;
            $issue->save();
            IssueStatusWebhook::dispatchIfChanged($issue, $before);
        }

        return $derived;
    }
}
