<?php

namespace App\Policies;

use App\Models\InternalTeam;
use App\Models\TeamDocument;
use App\Models\User;

class TeamDocumentPolicy
{
    public function viewAny(User $user, InternalTeam $team): bool
    {
        return $this->manage($user, $team);
    }

    public function create(User $user, InternalTeam $team): bool
    {
        return $this->manage($user, $team);
    }

    public function update(User $user, TeamDocument $document): bool
    {
        $document->loadMissing('team');

        return $document->team !== null && $this->manage($user, $document->team);
    }

    public function delete(User $user, TeamDocument $document): bool
    {
        return $this->update($user, $document);
    }

    private function manage(User $user, InternalTeam $team): bool
    {
        return (new InternalTeamPolicy)->view($user, $team);
    }
}
