<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Issue;
use App\Models\Unit;

/**
 * Domeinregel 2b: taak/melding hoort bij unit U als issue.unit_id = U óf U is ronde-stop.
 */
class TaskBelongsToUnitAction
{
    public function handle(Issue $issue, Unit $unit): bool
    {
        return Issue::query()
            ->whereKey($issue->id)
            ->belongsToUnit($unit)
            ->exists();
    }
}
