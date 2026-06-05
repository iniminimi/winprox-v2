<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Support\Audit\AuditRecorder;

class ActivateLocationAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Location $location, ?int $actorUserId = null): Location
    {
        $location->update(['is_active' => true]);

        $fresh = $location->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'location.activated',
            modelType: Location::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }
}
