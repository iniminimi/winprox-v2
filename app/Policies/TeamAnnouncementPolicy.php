<?php

namespace App\Policies;

use App\Models\InternalTeam;
use App\Models\TeamAnnouncement;
use App\Models\User;

class TeamAnnouncementPolicy
{
    public function viewAny(User $user, InternalTeam $team): bool
    {
        return $this->manage($user, $team);
    }

    public function create(User $user, InternalTeam $team): bool
    {
        return $this->manage($user, $team);
    }

    public function update(User $user, TeamAnnouncement $announcement): bool
    {
        $announcement->loadMissing('team');

        return $announcement->team !== null && $this->manage($user, $announcement->team);
    }

    public function delete(User $user, TeamAnnouncement $announcement): bool
    {
        return $this->update($user, $announcement);
    }

    private function manage(User $user, InternalTeam $team): bool
    {
        return (new InternalTeamPolicy)->view($user, $team);
    }
}
