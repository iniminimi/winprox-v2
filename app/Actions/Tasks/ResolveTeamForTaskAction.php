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
        // Get teams from the unit's category
        if ($unit->category === null) {
            return null;
        }

        $categoryTeams = $unit->category->teams();

        // If a preferred team is provided, return it if it's assigned to the category
        if ($preferredTeamId !== null) {
            $team = $categoryTeams->where('internal_teams.id', $preferredTeamId)->first();
            if ($team !== null) {
                return $team;
            }
        }

        // Return first assigned team from category
        return $categoryTeams->first();
    }

    /**
     * Get all teams that can work on this unit's tasks.
     * Used for claim-based task assignment.
     */
    public function getEligibleTeams(Unit $unit): Collection
    {
        if ($unit->category === null) {
            return collect();
        }

        return $unit->category->teams()->get();
    }
}
