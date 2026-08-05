<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;

/**
 * Intentie-flow: inspectieronde plannen (stops + interval + team) in één stap.
 * Onder water: terugkerende melding + stops + eerste teamtaak.
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

        $issuePayload = [
            'description' => $validated['description'],
            'is_recurring' => true,
            'recurrence_interval_value' => (int) $validated['recurrence_interval_value'],
            'recurrence_interval_unit' => (string) $validated['recurrence_interval_unit'],
            'recurrence_lead_days' => (int) $validated['recurrence_lead_days'],
            'recurrence_first_due_date' => (string) $validated['recurrence_first_due_date'],
            'round_stop_unit_ids' => $roundStopIds,
            'original_language' => $validated['original_language'] ?? null,
        ];

        $issue = $this->createIssue->handle($issuePayload, $actor);

        $this->assignTask->handle(
            $issue,
            (int) $validated['internal_team_id'],
            isset($validated['task_note']) ? (string) $validated['task_note'] : null,
            TaskPriority::from((string) $validated['task_priority']),
        );

        return $issue->fresh(['roundStops', 'tasks']);
    }
}
