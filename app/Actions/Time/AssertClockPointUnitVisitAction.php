<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

/**
 * GPS-werkbezoek on the customer location stands in for that unit's QR sticker.
 */
class AssertClockPointUnitVisitAction
{
    public function __construct(
        private FindOpenWorkShiftForWorkerAction $findShift,
    ) {}

    public function handle(Worker $worker, Unit $unit): void
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        $tenant = Tenant::query()->find((int) $worker->tenant_id);
        if ($tenant === null || ! $tenant->allowsGpsWorkVisits()) {
            throw new InvalidArgumentException('clock_point_task_read_only');
        }

        if ((int) $unit->tenant_id !== (int) $worker->tenant_id) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }

        $locationId = (int) $unit->location_id;
        if ($locationId <= 0 || ! $worker->canClockAt($locationId)) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }

        $shift = $this->findShift->handle($worker);
        $visit = $shift?->openVisit;
        if ($visit === null) {
            throw new InvalidArgumentException('clock_point_visit_required');
        }

        if ((int) $visit->location_id !== $locationId) {
            throw new InvalidArgumentException('clock_point_visit_location_mismatch');
        }
    }
}
