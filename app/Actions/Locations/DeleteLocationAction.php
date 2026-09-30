<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Support\Audit\AuditRecorder;
use App\Support\Locations\LocationDeletionGuard;
use InvalidArgumentException;

class DeleteLocationAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteUnitAction $deleteUnit,
    ) {}

    public function handle(Location $location, ?int $actorUserId = null): void
    {
        $reason = LocationDeletionGuard::blockReason($location);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        // Hier hangen hoogstens onaangeroerde site-units — die gaan mee weg
        // (o.a. import-undo na auto site-unit).
        foreach ($location->units()->get() as $unit) {
            $this->deleteUnit->handle($unit, $actorUserId);
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
