<?php

namespace App\Actions\Team;

use App\Actions\Locations\SyncUserLocationsAction;
use App\Models\InternalTeam;
use App\Models\User;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class CreateLinkedWorkerForUserAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SyncUserLocationsAction $syncLocations,
    ) {}

    /**
     * @param  list<int>  $locationIds
     */
    public function handle(
        User $user,
        InternalTeam $team,
        array $locationIds = [],
        ?int $actorUserId = null,
    ): Worker {
        if ((int) $user->tenant_id !== (int) $team->tenant_id) {
            throw new InvalidArgumentException('team_tenant_mismatch');
        }

        $existing = $user->linkedWorker;
        if ($existing !== null) {
            return $existing;
        }

        [$firstName, $lastName] = self::splitDisplayName((string) $user->name);

        $worker = Worker::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'internal_team_id' => $team->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'is_teamleader' => false,
            'is_external' => false,
        ]);

        if ($locationIds !== []) {
            $this->syncLocations->handleForWorker($worker, $locationIds, $actorUserId);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $user->tenant_id,
            action: 'worker.linked_to_user',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: ['id' => $worker->id, 'user_id' => $user->id, 'internal_team_id' => $team->id],
        );

        return $worker->fresh(['locations', 'team']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitDisplayName(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return ['-', '-'];
        }

        $parts = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return [$trimmed, '-'];
        }

        if (count($parts) === 1) {
            return [$parts[0], '-'];
        }

        $firstName = array_shift($parts);

        return [$firstName, implode(' ', $parts)];
    }
}
