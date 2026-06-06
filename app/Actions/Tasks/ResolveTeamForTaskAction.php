<?php

namespace App\Actions\Tasks;

use App\Models\InternalTeam;
use App\Models\Unit;

readonly class ResolveTeamForTaskAction
{
    public function __construct(
        private ResolveEligibleTeamsForUnitAction $resolveEligible,
    ) {
    }

    public function handle(
        Unit $unit,
        ?int $preferredTeamId = null,
    ): ?InternalTeam {
        // If a preferred team is provided, return it if it's eligible
        if ($preferredTeamId !== null) {
            $eligible = $this->resolveEligible->handle($unit);
            $preferred = $eligible->first(fn ($item) => $item['team']->id === $preferredTeamId);

            if ($preferred !== null) {
                return $preferred['team'];
            }
        }

        // Get eligible teams for the unit
        $eligible = $this->resolveEligible->handle($unit);

        // Try to find primary team
        $primary = $eligible->first(fn ($item) => $item['is_primary'] === true);

        if ($primary !== null) {
            return $primary['team'];
        }

        // If no primary team, fallback to unit's default team
        if ($unit->default_internal_team_id !== null) {
            $defaultTeam = InternalTeam::find($unit->default_internal_team_id);
            if ($defaultTeam !== null) {
                return $defaultTeam;
            }
        }

        // Fallback to first eligible team
        if ($eligible->isNotEmpty()) {
            return $eligible->first()['team'];
        }

        return null;
    }
}
