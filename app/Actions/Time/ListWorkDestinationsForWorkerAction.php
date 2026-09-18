<?php

namespace App\Actions\Time;

use App\Data\Time\WorkDestinationData;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Planned destinations for Clock Point "Waar moet ik vandaag naartoe": today's dated team work,
 * expanded inspection-round stops, plus today's published roster unit.
 * Undated open tasks stay out, except an undated inspection-round cycle
 * whose issue.recurrence_next_due_at falls today.
 */
class ListWorkDestinationsForWorkerAction
{
    /**
     * @return list<WorkDestinationData>
     */
    public function handle(Tenant $tenant, Worker $worker, ?Carbon $date = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if (! $tenant->allowsGpsWorkVisits()) {
            return [];
        }

        $worker->loadMissing(['team', 'locations']);
        $team = $worker->team;
        if (! $team instanceof InternalTeam) {
            return [];
        }

        $dayStart = ($date ?? now())->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();
        $radius = $tenant->gpsVisitRadiusMeters();

        /** @var array<string, WorkDestinationData> $byKey */
        $byKey = [];

        foreach ($this->todaysOpenTasks($team, $dayStart, $dayEnd) as $task) {
            $issue = $task->issue;
            if ($issue === null) {
                continue;
            }

            if ($issue->isInspectionRound()) {
                foreach ($issue->roundStops->sortBy('sort_order') as $stop) {
                    $this->offerUnit($byKey, $stop->unit, $worker, $radius);
                }

                continue;
            }

            if ($issue->unit instanceof Unit) {
                $this->offerUnit($byKey, $issue->unit, $worker, $radius);

                continue;
            }

            if ($issue->location instanceof Location) {
                $this->offerLocation($byKey, $issue->location, $worker, $radius);
            }
        }

        $this->offerTodaysRosterUnit($byKey, $tenant, $worker, $dayStart, $radius);

        $rows = array_values($byKey);
        usort($rows, function (WorkDestinationData $a, WorkDestinationData $b): int {
            $location = strcasecmp($a->locationName, $b->locationName);
            if ($location !== 0) {
                return $location;
            }

            return strcasecmp((string) $a->unitName, (string) $b->unitName);
        });

        return $rows;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Task>
     */
    private function todaysOpenTasks(InternalTeam $team, Carbon $dayStart, Carbon $dayEnd): \Illuminate\Support\Collection
    {
        return Task::query()
            ->forApprovedIssue()
            ->with([
                'issue.location',
                'issue.unit.location',
                'issue.roundStops.unit.location',
            ])
            ->where('tenant_id', $team->tenant_id)
            ->where('internal_team_id', $team->id)
            ->whereIn('status', TaskStatus::openValues())
            ->where(function (Builder $dateScoped) use ($dayStart, $dayEnd) {
                $dateScoped
                    ->whereDate('scheduled_for', $dayStart->toDateString())
                    ->orWhereBetween('due_at', [$dayStart, $dayEnd])
                    ->orWhere(function (Builder $roundDueToday) use ($dayStart, $dayEnd) {
                        $roundDueToday
                            ->whereNull('scheduled_for')
                            ->whereNull('due_at')
                            ->whereHas('issue', function (Builder $issueQuery) use ($dayStart, $dayEnd) {
                                $issueQuery
                                    ->has('roundStops', '>=', 2)
                                    ->whereBetween('recurrence_next_due_at', [$dayStart, $dayEnd]);
                            });
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function offerTodaysRosterUnit(
        array &$byKey,
        Tenant $tenant,
        Worker $worker,
        Carbon $dayStart,
        int $radius,
    ): void {
        $shift = PlannedShift::query()
            ->with('unit.location')
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published)
            ->whereDate('work_date', $dayStart->toDateString())
            ->where('kind', ShiftTypeKind::Work)
            ->whereNotNull('unit_id')
            ->orderBy('id')
            ->get();

        foreach ($shift as $row) {
            $this->offerUnit($byKey, $row->unit, $worker, $radius);
        }
    }

    /**
     * @param  array<string, WorkDestinationData>  $byKey
     */
    private function offerUnit(array &$byKey, ?Unit $unit, Worker $worker, int $radius): void
    {
        if (! $unit instanceof Unit || ! $unit->is_active) {
            return;
        }

        $location = $unit->location;
        if (! $location instanceof Location || ! $location->is_active) {
            return;
        }

        $locationId = (int) $location->id;
        if (! $worker->canClockAt($locationId)) {
            return;
        }

        $destination = $this->destinationFromUnit($unit, $location, $radius);
        if ($destination === null) {
            return;
        }

        $byKey[$destination->key] = $destination;
    }

    /**
     * @param  array<string, WorkDestinationData>  $byKey
     */
    private function offerLocation(array &$byKey, Location $location, Worker $worker, int $radius): void
    {
        if (! $location->is_active) {
            return;
        }

        $locationId = (int) $location->id;
        if (! $worker->canClockAt($locationId)) {
            return;
        }

        $destination = $this->destinationFromLocationOnly($location, $radius);
        if ($destination === null) {
            return;
        }

        $byKey[$destination->key] = $destination;
    }

    private function destinationFromUnit(Unit $unit, Location $location, int $radius): ?WorkDestinationData
    {
        $hasPin = $unit->hasWorkVisitPin();
        $address = $location->formattedAddress();
        if (! $hasPin && trim($address) === '') {
            return null;
        }

        $mapsUrl = $hasPin
            ? $this->mapsUrlFromCoordinates((float) $unit->latitude, (float) $unit->longitude)
            : $this->mapsUrlFromAddress($address);

        $locationName = trim($location->localizedName());
        if ($locationName === '') {
            $locationName = trim((string) $location->name);
        }

        $unitName = trim((string) $unit->localizedName());
        if ($unitName === '') {
            $unitName = trim((string) $unit->name);
        }

        return new WorkDestinationData(
            key: 'unit:'.(int) $unit->id,
            unitId: (int) $unit->id,
            locationId: (int) $location->id,
            locationName: $locationName,
            unitName: $unitName !== '' ? $unitName : null,
            addressLine: $address,
            mapsUrl: $mapsUrl,
            canStartVisit: $hasPin,
            pinLatitude: $hasPin ? (float) $unit->latitude : null,
            pinLongitude: $hasPin ? (float) $unit->longitude : null,
            radiusMeters: $radius,
        );
    }

    private function destinationFromLocationOnly(Location $location, int $radius): ?WorkDestinationData
    {
        $address = $location->formattedAddress();
        if (trim($address) === '') {
            return null;
        }

        $locationName = trim($location->localizedName());
        if ($locationName === '') {
            $locationName = trim((string) $location->name);
        }

        return new WorkDestinationData(
            key: 'location:'.(int) $location->id,
            unitId: null,
            locationId: (int) $location->id,
            locationName: $locationName,
            unitName: null,
            addressLine: $address,
            mapsUrl: $this->mapsUrlFromAddress($address),
            canStartVisit: false,
            pinLatitude: null,
            pinLongitude: null,
            radiusMeters: $radius,
        );
    }

    private function mapsUrlFromCoordinates(float $latitude, float $longitude): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='
            .rawurlencode($latitude.','.$longitude);
    }

    private function mapsUrlFromAddress(string $address): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($address);
    }
}
