<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Worker;

class CountWorkerAdminFollowUpAction
{
    /**
     * @param  list<int>|null  $actorLocationIds  null = alle locaties
     */
    public function handle(int $tenantId, ?array $actorLocationIds = null): int
    {
        return Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('needs_admin_follow_up', true)
            ->when($actorLocationIds !== null, fn ($query) => $query->whereHas(
                'locations',
                fn ($locations) => $locations->whereIn('locations.id', $actorLocationIds ?: [0]),
            ))
            ->count();
    }
}
