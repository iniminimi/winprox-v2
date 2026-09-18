<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Events\Time\WorkVisitStarted;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\WorkShift;
use App\Models\WorkVisit;
use App\Models\Worker;
use App\Support\Geo\DistanceMeters;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Verified work at a customer location, or at a unit that has its own pin.
 * GPS is checked here at click time. WorkShift = paid/workday time and is not location proof.
 */
class StartWorkVisitAction
{
    public function __construct(
        private EndWorkVisitAction $endWorkVisit,
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
    ) {}

    public function handle(
        Worker $worker,
        Unit|Location $place,
        float $latitude,
        float $longitude,
        ClockSource $source = ClockSource::Gps,
    ): WorkVisit {
        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        $tenant = Tenant::query()->find((int) $worker->tenant_id);
        if ($tenant === null || ! $tenant->allowsGpsWorkVisits()) {
            throw new InvalidArgumentException('gps_visits_disabled');
        }

        [$location, $unit, $pinLatitude, $pinLongitude] = $this->resolvePlace($place, $worker);

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('visit_gps_invalid');
        }

        $worker->loadMissing(['team', 'locations']);
        if (! $worker->canClockAt((int) $location->id)) {
            throw new InvalidArgumentException('worker_location_not_allowed');
        }

        $meters = DistanceMeters::between(
            $latitude,
            $longitude,
            $pinLatitude,
            $pinLongitude,
        );
        if ($meters > $tenant->gpsVisitRadiusMeters()) {
            throw new InvalidArgumentException('visit_unit_out_of_range');
        }

        $storedUnitId = $unit instanceof Unit && $unit->hasWorkVisitPin()
            ? (int) $unit->id
            : null;

        return DB::transaction(function () use ($worker, $location, $storedUnitId, $latitude, $longitude, $source) {
            Worker::query()->whereKey($worker->id)->lockForUpdate()->first();

            $shift = WorkShift::query()
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($shift === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            $open = WorkVisit::query()
                ->where('worker_id', $worker->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                $openUnitId = $open->unit_id !== null ? (int) $open->unit_id : null;
                if ((int) $open->location_id === (int) $location->id && $openUnitId === $storedUnitId) {
                    throw new InvalidArgumentException('visit_already_open');
                }

                $this->endWorkVisit->handle($worker, required: true, source: $source);
            }

            $visit = WorkVisit::create([
                'tenant_id' => $worker->tenant_id,
                'worker_id' => $worker->id,
                'work_shift_id' => $shift->id,
                'unit_id' => $storedUnitId,
                'location_id' => $location->id,
                'started_at' => now(),
                'start_latitude' => $latitude,
                'start_longitude' => $longitude,
                'clock_source' => $source,
            ]);

            $visit = $visit->fresh(['unit', 'location', 'workShift']);

            event(new WorkVisitStarted($visit));
            $this->enqueuePresence->handle(PresenceSourceEvent::VisitStart, $shift, visit: $visit);

            return $visit;
        });
    }

    /**
     * @return array{0: Location, 1: ?Unit, 2: float, 3: float}
     */
    private function resolvePlace(Unit|Location $place, Worker $worker): array
    {
        if ($place instanceof Location) {
            if ((int) $place->tenant_id !== (int) $worker->tenant_id) {
                throw new InvalidArgumentException('unit_tenant_mismatch');
            }
            if (! $place->is_active) {
                throw new InvalidArgumentException('unit_inactive');
            }
            if (! $place->hasWorkVisitPin()) {
                throw new InvalidArgumentException('unit_visit_pin_missing');
            }

            return [$place, null, (float) $place->latitude, (float) $place->longitude];
        }

        $unit = $place;
        if ((int) $unit->tenant_id !== (int) $worker->tenant_id) {
            throw new InvalidArgumentException('unit_tenant_mismatch');
        }
        if (! $unit->is_active) {
            throw new InvalidArgumentException('unit_inactive');
        }

        $unit->loadMissing('location');
        $location = $unit->location;
        if (! $location instanceof Location || $unit->location_id === null) {
            throw new InvalidArgumentException('unit_visit_pin_missing');
        }
        if (! $location->is_active) {
            throw new InvalidArgumentException('unit_inactive');
        }

        if ($unit->hasWorkVisitPin()) {
            return [$location, $unit, (float) $unit->latitude, (float) $unit->longitude];
        }

        if ($location->hasWorkVisitPin()) {
            return [$location, $unit, (float) $location->latitude, (float) $location->longitude];
        }

        throw new InvalidArgumentException('unit_visit_pin_missing');
    }
}
