<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class DeleteLocationAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Location $location, ?int $actorUserId = null): void
    {
        if ($location->units()->exists()) {
            throw new InvalidArgumentException('location_has_units');
        }

        if ($location->issues()->exists()) {
            throw new InvalidArgumentException('location_has_issues');
        }

        if ($location->documents()->exists() || $location->announcements()->exists() || $location->bulkBatches()->exists()) {
            throw new InvalidArgumentException('location_has_content');
        }

        $tenantId = (int) $location->tenant_id;
        $locationId = (int) $location->id;
        $name = (string) $location->name;

        $location->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location.deleted',
            modelType: Location::class,
            modelId: $locationId,
            payload: ['id' => $locationId, 'name' => $name],
        );
    }
}
