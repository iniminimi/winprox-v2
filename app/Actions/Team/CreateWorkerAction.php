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
    public function handle(InternalTeam $team, array $data, ?int $actorUserId = null, ?Worker $actorWorker = null): Worker
    {
        if ($actorWorker !== null) {
            if (! $actorWorker->is_teamleader || ! $actorWorker->is_active) {
                throw new \InvalidArgumentException('not_teamleader');
            }

            if ((int) $actorWorker->internal_team_id !== (int) $team->id) {
                throw new \InvalidArgumentException('wrong_team');
            }
        }

        $worker = Worker::create([
            'tenant_id' => $team->tenant_id,
            'internal_team_id' => $team->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.created',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: array_merge(
                ['id' => $worker->id, 'internal_team_id' => $worker->internal_team_id],
                $actorWorker !== null ? ['actor_worker_id' => $actorWorker->id] : []
            ),
        );

        return $worker;
    }
}
