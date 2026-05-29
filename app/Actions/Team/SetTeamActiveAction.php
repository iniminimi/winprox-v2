<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;

class SetTeamActiveAction
{
    public function handle(InternalTeam $team, bool $active): InternalTeam
    {
        $team->update(['is_active' => $active]);

        return $team;
    }
}
