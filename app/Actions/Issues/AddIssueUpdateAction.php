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
    public function handle(
        Issue $issue,
        string $description,
        ?int $workerId = null,
        ?int $userId = null,
        ?string $kind = null,
        ?int $taskId = null,
    ): IssueUpdate {
        // Prevent update creation for closed issues
        if ($issue->isClosed()) {
            throw new \InvalidArgumentException('Cannot add update to closed issue');
        }

        return $issue->updates()->create([
            'description' => $description,
            'worker_id' => $workerId,
            'user_id' => $userId,
            'kind' => $kind,
            'task_id' => $taskId,
        ]);
    }
}
