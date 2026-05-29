<?php

namespace App\Actions\Issues;

use App\Actions\Tasks\CreateTaskAction;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;

class CreateIssueAction
{
    public function __construct(private CreateTaskAction $createTask) {}

    /**
     * Maakt een melding aan en optioneel een taak per opgegeven team.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int>  $teamIds  interne teams die elk een taak krijgen
     */
    public function handle(array $data, array $teamIds = []): Issue
    {
        $issue = Issue::create([
            'location_id' => $data['location_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'description' => $data['description'],
        ]);

        event(new IssueCreated($issue));

        foreach ($teamIds as $teamId) {
            $this->createTask->handle($issue, $teamId);
        }

        $issue->recalculateStatus();

        return $issue->fresh();
    }
}
