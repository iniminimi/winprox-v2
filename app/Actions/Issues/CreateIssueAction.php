<?php

namespace App\Actions\Issues;

use App\Actions\Tasks\CreateTaskAction;
use App\Enums\IssueSource;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Support\Recurrence\RecurrenceSchedule;
use App\Support\Translation\LocaleSupport;

class CreateIssueAction
{
    public function __construct(
        private CreateTaskAction $createTask,
        private RecalculateIssueStatusAction $recalculateIssueStatus,
    ) {}

    /**
     * Maakt een melding aan en optioneel een taak per opgegeven team.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int>  $teamIds  interne teams die elk een taak krijgen
     */
    public function handle(array $data, array $teamIds = [], ?int $actorUserId = null): Issue
    {
        $source = IssueSource::tryFrom((string) ($data['source'] ?? IssueSource::Manager->value))
            ?? IssueSource::Manager;

        $recurring = RecurrenceSchedule::issueAttributesFromValidated($data);

        $issue = Issue::create([
            'location_id' => $data['location_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'description' => $data['description'],
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'source' => $source,
            ...$recurring,
        ]);

        event(new IssueCreated($issue, $actorUserId));

        foreach ($teamIds as $teamId) {
            $this->createTask->handle($issue, $teamId, duringIssueIntake: true);
        }

        $this->recalculateIssueStatus->handle($issue);

        return $issue->fresh();
    }
}
