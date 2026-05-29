<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;

class UpdateTeamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data): InternalTeam
    {
        $team->update([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $team->is_active),
        ]);

        return $team;
    }
}
