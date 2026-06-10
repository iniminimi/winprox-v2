<?php

namespace App\Actions\Portal;

use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Portal\WorkerIcon;

class FindWorkerByIconOnTeamAction
{
    public function handle(InternalTeam $team, string $iconSlug): ?Worker
    {
        $iconSlug = trim($iconSlug);
        if (! WorkerIcon::isValidSlug($iconSlug)) {
            return null;
        }

        return Worker::query()
            ->where('internal_team_id', $team->id)
            ->where('field_icon_slug', $iconSlug)
            ->where('is_active', true)
            ->first();
    }
}
