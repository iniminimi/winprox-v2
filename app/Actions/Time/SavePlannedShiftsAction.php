<?php

namespace App\Actions\Time;

use App\Data\Time\SavePlannedShiftsData;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeKind;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SavePlannedShiftsAction
{
    public function __construct(
        private ResolveRosterPeriodAction $resolvePeriod,
        private ParseRosterCellAction $parseCell,
        private AssertPlannedShiftNoOverlapAction $assertNoOverlap,
        private ListShiftTypesAction $listShiftTypes,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return list<PlannedShift>
     */
    public function handle(Tenant $tenant, SavePlannedShiftsData $data, ?int $actorUserId, ?array $actorLocationIds = null): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $period = $data->period === 'month' ? 'month' : 'week';
        [, , $dates] = $this->resolvePeriod->handle($data->weekStart, $period, $data->includeWeekends);
        $weekStart = $dates[0];
        $weekEnd = $dates[array_key_last($dates)];
        $dateSet = array_fill_keys($dates, true);
        $workerIds = array_values(array_unique(array_map('intval', $data->workerIds)));

        $this->assertWorkersBelongToTenant($tenant->id, $workerIds);
        $this->assertCompleteGrid($workerIds, $dates, $data->cells, $dateSet);

        $types = collect($this->listShiftTypes->handle((int) $tenant->id, false));
        $units = $this->rosterUnits((int) $tenant->id, $data->locationId, $actorLocationIds);
        $workers = Worker::query()
            ->with(['team', 'locations'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $workerIds ?: [0])
            ->get()
            ->keyBy('id');

        $parsedCells = [];
        $invalid = [];
        foreach ($data->cells as $cell) {
            $worker = $workers->get((int) $cell['worker_id']);
            $catalog = $units->filter(
                fn (Unit $unit) => $worker instanceof Worker && $worker->canClockAt((int) $unit->location_id),
            );
            $parsed = $this->parseCell->handle((string) $cell['raw'], $types, $catalog);
            if ($parsed->kind === RosterCellKind::Invalid) {
                $invalid[] = [
                    'worker_id' => (int) $cell['worker_id'],
                    'date' => (string) $cell['date'],
                    'raw' => (string) $cell['raw'],
                    'error' => $parsed->errorKey,
                    'count' => $parsed->ambiguousCount,
                ];
            }
            $parsedCells[] = ['cell' => $cell, 'parsed' => $parsed];
        }

        if ($invalid !== []) {
            throw new RosterValidationException('time.schedule.errors.invalid_cells', $invalid);
        }

        return DB::transaction(function () use ($tenant, $workerIds, $weekStart, $weekEnd, $parsedCells, $actorUserId, $dates) {
            $existing = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->lockForUpdate()
                ->get();

            $weekPublished = $existing->contains(fn (PlannedShift $shift) => $shift->status->isPublished());
            $status = $weekPublished ? PlannedShiftStatus::Published : PlannedShiftStatus::Draft;

            PlannedShift::query()
                ->whereIn('id', $existing->pluck('id')->all() ?: [0])
                ->delete();

            $created = [];
            $intervals = [];
            foreach ($parsedCells as $item) {
                $parsed = $item['parsed'];
                if ($parsed->isEmpty()) {
                    continue;
                }

                $shift = PlannedShift::create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => (int) $item['cell']['worker_id'],
                    'work_date' => $item['cell']['date'],
                    'shift_type_id' => $parsed->shiftTypeId,
                    'kind' => $parsed->shiftTypeKind ?? ShiftTypeKind::Work,
                    'unit_id' => $parsed->unitId,
                    'unit_code' => $parsed->unitCode,
                    'unit_name' => $parsed->unitName,
                    'location_id' => $parsed->locationId,
                    'start_time' => $parsed->startTime,
                    'end_time' => $parsed->endTime,
                    'break_minutes' => $parsed->breakMinutes,
                    'status' => $status,
                ]);
                $created[] = $shift;
                $intervals[] = [
                    'worker_id' => (int) $shift->worker_id,
                    'date' => $shift->work_date->toDateString(),
                    'start' => $shift->start_time,
                    'end' => $shift->end_time,
                    'kind' => $shift->kind->value,
                ];
            }

            $this->assertNoOverlap->handle($intervals);

            event(new ScheduleSaved(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                weekStart: $weekStart,
                workerIds: $workerIds,
                dates: $dates,
                count: count($created),
            ));

            return $created;
        });
    }

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return Collection<int, Unit>
     */
    private function rosterUnits(int $tenantId, ?int $locationId, ?array $actorLocationIds): Collection
    {
        return Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('location_id', $actorLocationIds ?: [0]))
            ->get();
    }

    /**
     * @param  list<int>  $workerIds
     */
    private function assertWorkersBelongToTenant(int $tenantId, array $workerIds): void
    {
        if ($workerIds === []) {
            return;
        }

        $count = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->count();

        if ($count !== count($workerIds)) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }
    }

    /**
     * @param  list<int>  $workerIds
     * @param  list<string>  $dates
     * @param  list<array{worker_id: int, date: string, raw: string}>  $cells
     * @param  array<string, true>  $dateSet
     */
    private function assertCompleteGrid(array $workerIds, array $dates, array $cells, array $dateSet): void
    {
        $expected = [];
        foreach ($workerIds as $workerId) {
            foreach ($dates as $date) {
                $expected[$workerId.':'.$date] = true;
            }
        }

        $seen = [];
        foreach ($cells as $cell) {
            $date = (string) $cell['date'];
            $workerId = (int) $cell['worker_id'];
            if (! isset($dateSet[$date]) || ! in_array($workerId, $workerIds, true)) {
                throw new RosterValidationException('time.schedule.errors.cell_out_of_scope');
            }
            $key = $workerId.':'.$date;
            if (isset($seen[$key])) {
                throw new RosterValidationException('time.schedule.errors.duplicate_cell');
            }
            $seen[$key] = true;
        }

        if (count($seen) !== count($expected)) {
            throw new RosterValidationException('time.schedule.errors.incomplete_grid');
        }
    }
}
