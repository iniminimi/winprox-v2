<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\IssueUpdate;

/**
 * Voegt een notitie/tijdlijn-item toe aan een melding. Vanuit het veldportaal
 * gebeurt dit met een worker_id (uitvoerder zonder login); vanuit beheer met user_id.
 */
class AddIssueUpdateAction
{
    public function handle(Issue $issue, string $body, ?int $workerId = null, ?int $userId = null, ?string $kind = null): IssueUpdate
    {
        return $issue->updates()->create([
            'body' => $body,
            'worker_id' => $workerId,
            'user_id' => $userId,
            'kind' => $kind,
        ]);
    }
}
