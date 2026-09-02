<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\User;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class SyncUserLocationsAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<int>  $locationIds
     */
    public function handle(User $user, array $locationIds, ?int $actorUserId = null): User
    {
        if ($user->hasUnrestrictedLocationAccess()) {
            $user->locations()->sync([]);

            return $user->fresh(['locations']);
        }

        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));
        $this->assertLocationsBelongToTenant($locationIds, (int) $user->tenant_id);

        $user->locations()->sync($locationIds);

        $fresh = $user->fresh(['locations']);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.locations_synced',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'location_ids' => $locationIds],
        );

        return $fresh;
    }

    /**
     * @param  list<int>  $locationIds
     */
    public function handleForWorker(Worker $worker, array $locationIds, ?int $actorUserId = null): Worker
    {
        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));
        $this->assertLocationsBelongToTenant($locationIds, (int) $worker->tenant_id);

        $worker->locations()->sync($locationIds);

        $fresh = $worker->fresh(['locations']);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.locations_synced',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'location_ids' => $locationIds],
        );

        return $fresh;
    }

    /**
     * @param  list<int>  $locationIds
     */
    private function assertLocationsBelongToTenant(array $locationIds, int $tenantId): void
    {
        if ($locationIds === []) {
            return;
        }

        $count = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $locationIds)
            ->count();

        if ($count !== count($locationIds)) {
            throw new InvalidArgumentException('location_invalid');
        }
    }
}
