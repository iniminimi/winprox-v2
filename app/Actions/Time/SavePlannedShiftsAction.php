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
use App\Support\Time\RosterHistoryLock;
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
     * @return array{shifts: list<PlannedShift>}
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
                $parsedCells[] = ['cell' => $cell, 'blocks' => null];

                continue;
            }
            $worker = $workers->get((int) $cell['worker_id']);
            $catalog = $units->filter(
                fn (Unit $unit) => $worker instanceof Worker && $worker->canClockAt((int) $unit->location_id),
            );
            $blocks = $this->parseCell->handleAll((string) $cell['raw'], $types, $catalog);
            foreach ($blocks as $parsed) {
                if ($parsed->kind === RosterCellKind::Invalid) {
                    $invalid[] = [
                        'worker_id' => (int) $cell['worker_id'],
                        'date' => (string) $cell['date'],
                        'raw' => (string) $cell['raw'],
                        'error' => $parsed->errorKey,
                        'count' => $parsed->ambiguousCount,
                    ];
                    break;
                }
            }
            $parsedCells[] = ['cell' => $cell, 'blocks' => $blocks];
        }

        if ($invalid !== []) {
            throw new RosterValidationException('time.schedule.errors.invalid_cells', $invalid);
        }

        return DB::transaction(function () use ($tenant, $workerIds, $weekStart, $weekEnd, $parsedCells, $keepCells, $actorUserId, $dates, $dateSet) {
            // Alleen rijen op zichtbare datums horen bij deze save. In een
            // maand zonder weekends liggen weekenddagen binnen [start, eind]
            // maar buiten de grid: die rijen worden niet verwijderd en niet
            // als vergrendelde wijziging gezien.
            $existing = PlannedShift::query()
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->lockForUpdate()
                ->get()
                ->filter(fn (PlannedShift $shift) => isset($dateSet[$shift->work_date->toDateString()]))
                ->values();

            // Elke gewijzigde cel wordt draft — ook in een al gepubliceerde
            // periode. Zo blijft "Niet gepubliceerd" zichtbaar tot de beheerder
            // bewust publiceert; uitvoerders zien de wijziging pas dan.
            $status = PlannedShiftStatus::Draft;

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
                $blocks = $item['blocks'];
                if ($blocks === null || count($blocks) !== 1 || $blocks[0]->isEmpty()) {
                    continue;
                }
                $dayKey = (int) $item['cell']['worker_id'].':'.(string) $item['cell']['date'];
                if (isset($preservedDays[$dayKey])) {
                    continue;
                }
                $rows = $existingByDay->get($dayKey);
                if ($rows !== null && $rows->count() === 1 && $this->parsedMatchesRow($blocks[0], $rows->first())) {
                    $preservedIds[] = (int) $rows->first()->id;
                    $preservedDays[$dayKey] = true;
                }
            }

            // Historische dagen (< vandaag, app-tijdzone) zijn vergrendeld.
            // Een keep-cel of een cel die identiek is aan de bestaande rij
            // telt als "ongewijzigd"; elke andere inhoud op zo'n dag —
            // wijzigen, legen of op een lege dag plannen — is een
            // wijzigingspoging en wordt als cel geflagd. Rijen op vergrendelde
            // dagen die niet in de payload zitten, worden nooit verwijderd.
            $lockedErrors = [];
            foreach ($existingByDay as $dayKey => $rows) {
                $date = (string) str($dayKey)->after(':');
                if (! RosterHistoryLock::isLockedDate($date) || isset($preservedDays[$dayKey])) {
                    continue;
                }
                $lockedErrors[] = [
                    'worker_id' => (int) str($dayKey)->before(':')->toString(),
                    'date' => $date,
                    'raw' => '',
                    'error' => 'time.schedule.errors.locked_days',
                ];
            }
            foreach ($parsedCells as $item) {
                $blocks = $item['blocks'];
                if ($blocks === null) {
                    continue;
                }
                $date = (string) $item['cell']['date'];
                if (! RosterHistoryLock::isLockedDate($date)) {
                    continue;
                }
                $dayKey = (int) $item['cell']['worker_id'].':'.$date;
                if (isset($preservedDays[$dayKey]) || isset($existingByDay[$dayKey])) {
                    continue;
                }
                $hasContent = collect($blocks)->contains(
                    fn (RosterCellData $parsed) => ! $parsed->isEmpty(),
                );
                if ($hasContent) {
                    $lockedErrors[] = [
                        'worker_id' => (int) $item['cell']['worker_id'],
                        'date' => $date,
                        'raw' => (string) $item['cell']['raw'],
                        'error' => 'time.schedule.errors.locked_days',
                    ];
                }
            }
            if ($lockedErrors !== []) {
                throw new RosterValidationException('time.schedule.errors.locked_days', $lockedErrors);
            }

            PlannedShift::query()
                ->whereIn('id', array_diff($existing->pluck('id')->all(), $preservedIds) ?: [0])
                ->delete();

            $created = [];
            $intervals = [];
            foreach ($parsedCells as $item) {
                $blocks = $item['blocks'];
                if ($blocks === null) {
                    continue;
                }
                $dayKey = (int) $item['cell']['worker_id'].':'.(string) $item['cell']['date'];
                if (isset($preservedDays[$dayKey])) {
                    continue;
                }

                foreach ($blocks as $parsed) {
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

            return ['shifts' => $created];
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
