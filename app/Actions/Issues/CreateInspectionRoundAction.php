<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;

/**
 * Intentie-flow: inspectieronde plannen (stops + team) in één stap.
 * Onder water: melding met stops + eerste teamtaak (terugkerend of eenmalig).
 */
class CreateInspectionRoundAction
{
    public function __construct(
        private CreateManagerIssueAction $createIssue,
        private AssignIssueTeamTaskAction $assignTask,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated, User $actor): Issue
    {
        $roundStopIds = array_values(array_unique(array_map(
            'intval',
            $validated['round_stop_unit_ids'] ?? [],
        )));

        $isRecurring = array_key_exists('is_recurring', $validated)
            ? (bool) $validated['is_recurring']
            : true;

        $issuePayload = [
            'description' => $validated['description'],
            'is_recurring' => $isRecurring,
            'recurrence_first_due_date' => (string) $validated['recurrence_first_due_date'],
            'round_stop_unit_ids' => $roundStopIds,
            'original_language' => $validated['original_language'] ?? null,
        ];

        if ($isRecurring) {
            $issuePayload['recurrence_interval_value'] = (int) $validated['recurrence_interval_value'];
            $issuePayload['recurrence_interval_unit'] = (string) $validated['recurrence_interval_unit'];
            $issuePayload['recurrence_lead_days'] = (int) $validated['recurrence_lead_days'];
        }

        $issue = $this->createIssue->handle($issuePayload, $actor);

        $this->assignTask->handle(
            $issue,
            (int) $validated['internal_team_id'],
            isset($validated['task_note']) ? (string) $validated['task_note'] : null,
            TaskPriority::from((string) $validated['task_priority']),
            assignedWorkerId: isset($validated['assigned_worker_id'])
                ? (int) $validated['assigned_worker_id']
                : null,
        );

        return $issue->fresh(['roundStops', 'tasks']);
    }
}
