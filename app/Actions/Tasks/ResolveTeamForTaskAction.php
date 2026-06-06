<?php

namespace App\Actions\Tasks;

use App\Models\InternalTeam;
use App\Models\Unit;
use Illuminate\Support\Collection;

readonly class ResolveTeamForTaskAction
{
    public function handle(
        Unit $unit,
        ?int $preferredTeamId = null,
    ): ?InternalTeam {
        // If a preferred team is provided, return it if it's assigned to the unit
        if ($preferredTeamId !== null) {
            $team = $unit->teams()->where('internal_teams.id', $preferredTeamId)->first();
            if ($team !== null) {
                return $team;
            }
        }

        // Return first assigned team (claim-based: teams can claim the task)
        return $unit->teams()->first();
    }

    /**
     * Get all teams that can work on this unit's tasks.
     * Used for claim-based task assignment.
     */
    public function getEligibleTeams(Unit $unit): Collection
    {
        return $unit->teams()->get();
    }
}
