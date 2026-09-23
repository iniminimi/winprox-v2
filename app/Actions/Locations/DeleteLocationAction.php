<?php

namespace App\Actions\Locations;

use App\Models\EsgMeasurement;
use App\Models\IssueRoundStop;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitDeletionGuard;
use InvalidArgumentException;

class DeleteLocationAction
{
    public function __construct(
        private AuditRecorder $audit,
        private DeleteUnitAction $deleteUnit,
    ) {}

    public function handle(Location $location, ?int $actorUserId = null): void
    {
        $this->removePristineSiteUnitsOrFail($location, $actorUserId);

        if ($location->units()->exists()) {
            throw new InvalidArgumentException('location_has_units');
        }

        if ($location->issues()->exists()) {
            throw new InvalidArgumentException('location_has_issues');
        }

        if ($location->documents()->exists() || $location->announcements()->exists() || $location->bulkBatches()->exists()) {
            throw new InvalidArgumentException('location_has_content');
        }

        if (EsgMeasurement::query()->where('location_id', $location->id)->exists()) {
            throw new InvalidArgumentException('location_has_esg_measurements');
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

    /**
     * Locaties met enkel onaangeroerde site-units (geen issues/rondes) mogen
     * mee gewist worden — o.a. import-undo na auto site-unit.
     */
    private function removePristineSiteUnitsOrFail(Location $location, ?int $actorUserId): void
    {
        $units = $location->units()->get();
        if ($units->isEmpty()) {
            return;
        }

        $onlyPristineSiteUnits = $units->every(
            fn (Unit $unit): bool => (bool) $unit->is_site_unit
                && UnitDeletionGuard::canDelete($unit)
                && ! IssueRoundStop::query()->where('unit_id', $unit->id)->exists()
                && ! $unit->reservations()->exists()
                && ! $unit->documents()->exists()
                && ! $unit->announcements()->exists()
        );

        if (! $onlyPristineSiteUnits) {
            throw new InvalidArgumentException('location_has_units');
        }

        foreach ($units as $unit) {
            $this->deleteUnit->handle($unit, $actorUserId);
        }
    }
}
