<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rangschikt vervanger-kandidaten voor één of meerdere werkblokken van dezelfde
 * worker×dag. Alleen wie die dag een shifttype uit de vervangingspool heeft,
 * plus — als de tenant dat aanzet — wie die dag geen blok heeft.
 * Een poolblok is beschikbaarheid: het telt niet als overlappend werk.
 * Ziekte, een gewoon werkblok dat de gevraagde uren overlapt, een afwezigheid
 * buiten de pool, een lopende aanvraag zonder poolblok, een andere locatie
 * en een expliciete 0-minutenweekdag blijven uitsluiten.
 * Rang: 1) zelfde unit (default_unit) → 2) toegewezen op de blok-locatie → 3) rest.
 */
class SuggestReplacementAction
{

    /**
     * @param  list<int>  $plannedShiftIds  werkblokken van dezelfde worker×dag
     * @return list<array{worker_id: int, name: string, tier: int, unit_code: ?string, shift_label: string}>
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

        $dayShifts = PlannedShift::query()
            ->with('shiftType')
            ->where('tenant_id', $tenant->id)
            ->whereDate('work_date', $date)
            ->where('worker_id', '!=', $workerId)
            ->whereIn('worker_id', $candidateIds ?: [0])
            ->get()
            ->groupBy(fn (PlannedShift $shift) => (int) $shift->worker_id);

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

        // Alleen een expliciete werkregelingsrij met 0 minuten op de ISO-
        // weekdag van de datum sluit een kandidaat uit; zonder patroon
        // (geen rijen) is er geen uitsluiting.
        $offDayWorkerIds = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $candidateIds ?: [0])
            ->where('weekday', (int) Carbon::parse($date)->dayOfWeekIso)
            ->where('minutes', 0)
            ->pluck('worker_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $includeUnplanned = (bool) $tenant->time_replacement_include_unplanned;
        $absent = array_flip($absentWorkerIds);
        $offDay = array_flip($offDayWorkerIds);

        return Worker::query()
            ->with(['locations', 'defaultUnit', 'tenant'])
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('id', '!=', $workerId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->reject(fn (Worker $worker) => isset($offDay[(int) $worker->id]))
            ->filter(function (Worker $worker) use ($locationIds) {
                foreach ($locationIds as $locationId) {
                    if (! $worker->canClockAt($locationId)) {
                        return false;
                    }
                }

                return true;
            })
            ->filter(function (Worker $worker) use ($dayShifts, $windows, $includeUnplanned, $absent) {
                /** @var Collection<int, PlannedShift> $blocks */
                $blocks = $dayShifts->get((int) $worker->id, collect());
                if ($blocks->contains(fn (PlannedShift $shift) => $shift->kind === ShiftTypeKind::Sick)) {
                    return false;
                }
                if ($blocks->contains(fn (PlannedShift $shift) => $shift->kind->isAbsence() && ! $shift->isReplacementPool())) {
                    return false;
                }
                if ($blocks->contains(fn (PlannedShift $shift) => $this->blocksRequestedWindow($shift, $windows))) {
                    return false;
                }

                $hasPool = $blocks->contains(fn (PlannedShift $shift) => $shift->isReplacementPool());
                if ($hasPool) {
                    return true;
                }

                return $includeUnplanned
                    && $blocks->isEmpty()
                    && ! isset($absent[(int) $worker->id]);
            })
            ->map(function (Worker $worker) use ($unitIds, $locationIds, $dayShifts) {
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
                    'shift_label' => $this->shiftLabel($dayShifts->get((int) $worker->id, collect())),
                ];
            })
            ->sortBy([['tier', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PlannedShift>  $blocks
     */
    private function shiftLabel(Collection $blocks): string
    {
        return $blocks
            ->sortBy(fn (PlannedShift $shift) => sprintf(
                '%d-%s',
                $shift->start_time === null ? 0 : 1,
                (string) $shift->start_time,
            ))
            ->map(function (PlannedShift $shift): ?string {
                $name = trim((string) ($shift->shiftType?->label ?: $shift->shiftType?->code));
                if ($name === '') {
                    return null;
                }
                if ($shift->kind->isWork() && $shift->start_time !== null && $shift->end_time !== null) {
                    return $name.' '.substr((string) $shift->start_time, 0, 5).'–'.substr((string) $shift->end_time, 0, 5);
                }

                return $name;
            })
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * Gewoon werk dat de gevraagde uren raakt. Een poolblok is beschikbaarheid.
     *
     * @param  list<array{0: int, 1: int}>  $windows
     */
    private function blocksRequestedWindow(PlannedShift $shift, array $windows): bool
    {
        if (! $shift->kind->isWork() || $shift->isReplacementPool()) {
            return false;
        }

        foreach ($windows as [$start, $end]) {
            if ($shift->overlapsMinutes($start, $end)) {
                return true;
            }
        }

        return false;
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
