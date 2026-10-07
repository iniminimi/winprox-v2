<?php

namespace App\Actions\Time;

use App\Data\Time\RosterCellData;
use App\Data\Time\SavePlannedShiftsData;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterCellKind;
use App\Enums\ShiftTypeKind;
use App\Events\Time\ScheduleSaved;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Time\RosterDaySignature;
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
        private NotifyWorkersRosterChangedAction $notifyChanged,
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
        $keepCells = [];
        foreach ($data->cells as $cell) {
            $cellKey = (int) $cell['worker_id'].':'.(string) $cell['date'];
            if (! empty($cell['keep'])) {
                // Ongewijzigde multi-blok-dag: rijen behouden hun ids
                // (notities/attendance blijven hangen); cel niet parsen.
                $keepCells[$cellKey] = true;
                $parsedCells[] = ['cell' => $cell, 'parsed' => null];

                continue;
            }
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

        return DB::transaction(function () use ($tenant, $workerIds, $weekStart, $weekEnd, $parsedCells, $keepCells, $actorUserId, $dates) {
            $existing = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->lockForUpdate()
                ->get();

            $weekPublished = $existing->contains(fn (PlannedShift $shift) => $shift->status->isPublished());
            $status = $weekPublished ? PlannedShiftStatus::Published : PlannedShiftStatus::Draft;

            $existingByDay = $existing->groupBy(
                fn (PlannedShift $shift) => $shift->worker_id.':'.$shift->work_date->toDateString(),
            );
            $preservedIds = [];
            $preservedDays = [];
            foreach ($existingByDay as $dayKey => $rows) {
                if (isset($keepCells[$dayKey])) {
                    foreach ($rows as $row) {
                        $preservedIds[] = $row->id;
                    }
                    $preservedDays[$dayKey] = true;
                }
            }

            // Eén-blok-cel die identiek is aan het bestaande blok behoudt zijn
            // rij: zo overleeft de notitie (description) een grid-save die de
            // cel inhoudelijk niet wijzigt.
            foreach ($parsedCells as $item) {
                $parsed = $item['parsed'];
                if ($parsed === null || $parsed->isEmpty()) {
                    continue;
                }
                $dayKey = (int) $item['cell']['worker_id'].':'.(string) $item['cell']['date'];
                if (isset($preservedDays[$dayKey])) {
                    continue;
                }
                $rows = $existingByDay->get($dayKey);
                if ($rows !== null && $rows->count() === 1 && $this->parsedMatchesRow($parsed, $rows->first())) {
                    $preservedIds[] = (int) $rows->first()->id;
                    $preservedDays[$dayKey] = true;
                }
            }

            PlannedShift::query()
                ->whereIn('id', array_diff($existing->pluck('id')->all(), $preservedIds) ?: [0])
                ->delete();

            $created = [];
            $intervals = [];
            foreach ($parsedCells as $item) {
                $parsed = $item['parsed'];
                if ($parsed === null || $parsed->isEmpty()) {
                    continue;
                }
                $dayKey = (int) $item['cell']['worker_id'].':'.(string) $item['cell']['date'];
                if (isset($preservedDays[$dayKey])) {
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

            $this->notifyChangedPublishedDays(
                $tenant,
                $existingByDay,
                $parsedCells,
                $preservedDays,
                $weekPublished,
            );

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
     * Geparseerde cel == bestaand blok? Vergelijkt de inhoud, zonder de
     * notitie (description): de cel kan die niet dragen, dus een match
     * behoudt de rij — inclusief notitie — ongewijzigd.
     */
    private function parsedMatchesRow(RosterCellData $parsed, PlannedShift $row): bool
    {
        return ShiftType::formatTime($row->start_time) === ShiftType::formatTime($parsed->startTime)
            && ShiftType::formatTime($row->end_time) === ShiftType::formatTime($parsed->endTime)
            && $row->kind->value === ($parsed->shiftTypeKind ?? ShiftTypeKind::Work)->value
            && (int) ($row->shift_type_id ?? 0) === (int) ($parsed->shiftTypeId ?? 0)
            && (int) ($row->unit_id ?? 0) === (int) ($parsed->unitId ?? 0)
            && (int) $row->break_minutes === (int) ($parsed->breakMinutes ?? 0);
    }

    /**
     * RosterChanged-melding per gewijzigde published dag. Vergelijkt de
     * inhoudelijke dag-signature voor/na; bewaarde (keep of identieke
     * enkelvoudige cel) dagen zijn per definitie ongewijzigd.
     *
     * @param  Collection<string, Collection<int, PlannedShift>>  $existingByDay
     * @param  list<array{cell: array<string, mixed>, parsed: ?\App\Data\Time\RosterCellData}>  $parsedCells
     * @param  array<string, true>  $preservedDays
     */
    private function notifyChangedPublishedDays(
        Tenant $tenant,
        Collection $existingByDay,
        array $parsedCells,
        array $preservedDays,
        bool $weekPublished,
    ): void {
        $afterByDay = [];
        foreach ($parsedCells as $item) {
            $key = (int) $item['cell']['worker_id'].':'.(string) $item['cell']['date'];
            $parsed = $item['parsed'];
            $afterByDay[$key] = $parsed === null || $parsed->isEmpty() ? [] : [[
                'start' => $parsed->startTime,
                'end' => $parsed->endTime,
                'kind' => ($parsed->shiftTypeKind ?? ShiftTypeKind::Work)->value,
                'type' => $parsed->shiftTypeId,
                'unit' => $parsed->unitId,
            ]];
        }

        $pairs = [];
        foreach ($afterByDay as $key => $afterBlocks) {
            if (isset($preservedDays[$key])) {
                continue;
            }

            $beforeRows = $existingByDay->get($key, collect());
            $beforeSignature = RosterDaySignature::of($beforeRows->values());
            $afterSignature = collect($afterBlocks)
                ->map(fn (array $block) => implode('|', [
                    ShiftType::formatTime($block['start']),
                    ShiftType::formatTime($block['end']),
                    $block['kind'],
                    (string) $block['type'],
                    (string) $block['unit'],
                    '',
                ]))
                ->sort()
                ->implode('||');

            if ($beforeSignature === $afterSignature) {
                continue;
            }

            $beforePublished = $beforeRows->contains(
                fn (PlannedShift $shift) => $shift->status->isPublished(),
            );
            if (! $beforePublished && ! ($weekPublished && $afterBlocks !== [])) {
                continue;
            }

            [$workerId, $date] = explode(':', $key, 2);
            $pairs[] = ['worker_id' => (int) $workerId, 'date' => $date];
        }

        if ($pairs !== []) {
            $this->notifyChanged->handle($tenant, $pairs);
        }
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
