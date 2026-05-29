<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Models\Worker;

/**
 * Voegt een worker (uitvoerder zonder login) toe aan een team. De worker
 * bevestigt later zelf een persoonlijk icoon via het team-QR-portaal.
 */
class CreateWorkerAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data): Worker
    {
        return Worker::create([
            'tenant_id' => $team->tenant_id,
            'internal_team_id' => $team->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'is_active' => true,
        ]);
    }
}
