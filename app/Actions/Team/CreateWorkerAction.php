<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

/**
 * Voegt een worker (uitvoerder zonder login) toe aan een team. De worker
 * bevestigt later zelf een persoonlijk icoon via het team-QR-portaal.
 */
class CreateWorkerAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data, ?int $actorUserId = null): Worker
    {
        $worker = Worker::create([
            'tenant_id' => $team->tenant_id,
            'internal_team_id' => $team->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'is_active' => true,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.created',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: ['id' => $worker->id, 'internal_team_id' => $worker->internal_team_id],
        );

        return $worker;
    }
}
