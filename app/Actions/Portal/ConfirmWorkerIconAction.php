<?php

namespace App\Actions\Portal;

use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Portal\WorkerIcon;

class ConfirmWorkerIconAction
{
    public function handle(InternalTeam $team, Worker $worker, string $iconSlug): ?Worker
    {
        $iconSlug = trim($iconSlug);
        $expected = trim((string) $worker->field_icon_slug);

        if (! WorkerIcon::isValidSlug($iconSlug) || $expected === '' || $iconSlug !== $expected) {
            return null;
        }

        if ((int) $worker->internal_team_id !== (int) $team->id) {
            return null;
        }

        return $worker;
    }
}
