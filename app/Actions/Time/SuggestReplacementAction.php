<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;

/**
 * Rangschikt vervanger-kandidaten voor één of meerdere werkblokken van dezelfde
 * worker×dag. Kandidaten moeten elk tijdslot kunnen overnemen:
 * - actief, andere worker, kan klokken op elke blok-locatie
 * - geen overlappend gepland blok en geen absence-blok die dag
 * - geen lopende/goedgekeurde afwezigheidsaanvraag die dag
 * - niet structureel onbeschikbaar op die weekdag
 * Rang: 1) zelfde unit (default_unit) → 2) toegewezen op de blok-locatie → 3) rest.
 */
class SuggestReplacementAction
{
    public function __construct(
        private ListWorkerUnavailableDatesAction $listUnavailableDates,
    ) {}

    /**
     * @param  list<int>  $plannedShiftIds  werkblokken van dezelfde worker×dag
     * @return list<array{worker_id: int, name: string, tier: int, unit_code: ?string}>
     */
    public function handle(Tenant $tenant, array $plannedShiftIds): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $blocks = $this->loadWorkBlocks($tenant, $plannedShiftIds);

        /** @var PlannedShift $first */
        $first = $blocks->first();
        $workerId = (int) $first->worker_id;
        $date = $first->work_date->toDateString();

        $windows = $blocks
            ->map(fn (PlannedShift $b) => [
                ShiftType::timeToMinutes((string) $b->start_time),
                ShiftType::timeToMinutes((string) $b->end_time),
            ])
            ->all();
        $unitIds = $blocks->pluck('unit_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $locationIds = $blocks->pluck('location_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $candidateIds = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Werker-dag combinaties die een kandidaat diskwalificeren.
        $busyWorkerIds = PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('work_date', $date)
            ->where('worker_id', '!=', $workerId)
            ->whereIn('worker_id', $candidateIds ?: [0])
            ->get(['worker_id', 'start_time', 'end_time', 'kind'])
            ->filter(function (PlannedShift $shift) use ($windows) {
                if ($shift->kind->isAbsence()) {
                    // Elk afwezigheidsblok die dag (aanvraag óf omgezet) = afwezig.
                    return true;
                }
                $start = ShiftType::timeToMinutes((string) $shift->start_time);
                $end = ShiftType::timeToMinutes((string) $shift->end_time);
                foreach ($windows as [$wStart, $wEnd]) {
                    if ($start < $wEnd && $wStart < $end) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('worker_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $absentWorkerIds = AbsenceRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                AbsenceRequestStatus::Approved->value,
                AbsenceRequestStatus::Pending->value,
            ])
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->pluck('worker_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $unavailableWorkerIds = array_keys($this->listUnavailableDates->handle(
            (int) $tenant->id,
            $candidateIds,
            $date,
            $date,
        ));

        $excluded = array_flip(array_merge($busyWorkerIds, $absentWorkerIds, $unavailableWorkerIds, [$workerId]));

        return Worker::query()
            ->with(['locations', 'defaultUnit', 'tenant'])
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('id', '!=', $workerId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->reject(fn (Worker $worker) => isset($excluded[(int) $worker->id]))
            ->filter(function (Worker $worker) use ($locationIds) {
                foreach ($locationIds as $locationId) {
                    if (! $worker->canClockAt($locationId)) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function (Worker $worker) use ($unitIds, $locationIds) {
                $tier = 3;
                if ($unitIds !== [] && in_array((int) $worker->default_unit_id, $unitIds, true)) {
                    $tier = 1;
                } elseif ($locationIds !== [] && ! $worker->clocksAllLocations()) {
                    $assigned = $worker->locations->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (array_intersect($locationIds, $assigned) !== []) {
                        $tier = 2;
                    }
                }

                return [
                    'worker_id' => (int) $worker->id,
                    'name' => $worker->displayName(),
                    'tier' => $tier,
                    'unit_code' => $worker->defaultUnit?->roster_code,
                ];
            })
            ->sortBy([['tier', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $plannedShiftIds
     * @return Collection<int, PlannedShift>
     */
    private function loadWorkBlocks(Tenant $tenant, array $plannedShiftIds): Collection
    {
        $blocks = PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $plannedShiftIds ?: [0])
            ->get();

        if ($blocks->count() !== count(array_unique($plannedShiftIds)) || $blocks->isEmpty()) {
            throw new \InvalidArgumentException('time.schedule.errors.unknown_block');
        }

        $workerIds = $blocks->pluck('worker_id')->unique();
        $dates = $blocks->map(fn (PlannedShift $b) => $b->work_date->toDateString())->unique();
        if ($workerIds->count() !== 1 || $dates->count() !== 1) {
            throw new \InvalidArgumentException('time.schedule.errors.mixed_replacement_blocks');
        }

        foreach ($blocks as $block) {
            if (! $block->kind->isWork() || $block->start_time === null || $block->end_time === null) {
                throw new \InvalidArgumentException('time.schedule.errors.mixed_replacement_blocks');
            }
        }

        return $blocks;
    }
}
