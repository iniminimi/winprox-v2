<?php

namespace App\Actions\Issues;

use App\Enums\IssueSource;
use App\Events\Issues\IssueCreated;
use App\Models\Issue;
use App\Models\User;
use App\Support\Recurrence\RecurrenceSchedule;

/**
 * Stap 1 facility-flow: melding aanmaken door beheer (source=manager, direct goedgekeurd).
 */
class CreateManagerIssueAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Issue
    {
        $recurring = RecurrenceSchedule::issueAttributesFromValidated($data);

        $issue = Issue::create([
            'location_id' => $data['location_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'description' => $data['description'],
            'source' => IssueSource::Manager,
            'reporter_name' => $actor->name,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            ...$recurring,
        ]);

        event(new IssueCreated($issue));

        return $issue->fresh();
    }
}
