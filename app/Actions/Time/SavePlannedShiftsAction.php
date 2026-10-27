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

        return DB::transaction(function () use ($tenant, $data, $types, $units, $workers, $workerIds, $weekStart, $weekEnd, $actorUserId, $dates, $dateSet) {
            // Alleen rijen op zichtbare datums horen bij deze save. In een
            // maand zonder weekends liggen weekenddagen binnen [start, eind]
            // maar buiten de grid: die rijen worden niet verwijderd en niet
            // als vergrendelde wijziging gezien.
            $existing = PlannedShift::query()
                ->with('shiftType')
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

            // Per cel worden de blokken één op één met de bestaande rijen
            // vergeleken (server is de bron van waarheid). Een overeenkomend
            // blok behoudt zijn rij — id, notitie, pauze en status — en alleen
            // de rest wordt vervangen. Historische dagen (< vandaag,
            // app-tijdzone) zijn vergrendeld: elke werkelijke wijziging —
            // wijzigen, legen of op een lege dag plannen — wordt als cel
            // geflagd.
            $preservedIds = [];
            $invalid = [];
            $lockedErrors = [];
            $pending = [];
            foreach ($data->cells as $cell) {
                $workerId = (int) $cell['worker_id'];
                $date = (string) $cell['date'];
                /** @var Collection<int, PlannedShift> $rows */
                $rows = $existingByDay->get($workerId.':'.$date) ?? collect();

                if (! empty($cell['keep'])) {
                    // Ongewijzigde multi-blok-dag uit de grid: niet parsen.
                    foreach ($rows as $row) {
                        $preservedIds[] = (int) $row->id;
                    }

                    continue;
                }

                $worker = $workers->get($workerId);
                $catalog = $units->filter(
                    fn (Unit $unit) => $worker instanceof Worker && $worker->canClockAt((int) $unit->location_id),
                );
                $unmatched = $rows->keyBy('id');
                $kept = [];
                $new = [];
                $error = null;
                foreach ($this->parseCell->handleAll((string) $cell['raw'], $types, $catalog) as $parsed) {
                    if ($parsed->isEmpty()) {
                        continue;
                    }
                    $match = $unmatched->first(fn (PlannedShift $row) => $this->blockMatchesRow($parsed, $row));
                    if ($match !== null) {
                        $unmatched->forget($match->id);
                        $kept[] = $match;

                        continue;
                    }
                    if ($parsed->kind === RosterCellKind::Invalid) {
                        $error = $parsed;
                        break;
                    }
                    $new[] = $parsed;
                }

                if ($error !== null) {
                    $invalid[] = [
                        'worker_id' => $workerId,
                        'date' => $date,
                        'raw' => (string) $cell['raw'],
                        'error' => $error->errorKey,
                        'count' => $error->ambiguousCount,
                    ];

                    continue;
                }

                foreach ($kept as $row) {
                    $preservedIds[] = (int) $row->id;
                }
                if ($new === [] && $unmatched->isEmpty()) {
                    continue;
                }

                if (RosterHistoryLock::isLockedDate($date)) {
                    $lockedErrors[] = [
                        'worker_id' => $workerId,
                        'date' => $date,
                        'raw' => $rows->isEmpty() ? (string) $cell['raw'] : '',
                        'error' => 'time.schedule.errors.locked_days',
                    ];

                    continue;
                }

                $pending[] = ['cell' => $cell, 'blocks' => $new, 'kept' => $kept];
            }

            if ($invalid !== []) {
                throw new RosterValidationException('time.schedule.errors.invalid_cells', $invalid);
            }
            if ($lockedErrors !== []) {
                throw new RosterValidationException('time.schedule.errors.locked_days', $lockedErrors);
            }

            PlannedShift::query()
                ->whereIn('id', array_diff($existing->pluck('id')->all(), $preservedIds) ?: [0])
                ->delete();

            $created = [];
            $intervals = [];
            foreach ($pending as $item) {
                foreach ($item['kept'] as $row) {
                    $intervals[] = $this->interval($row);
                }
                foreach ($item['blocks'] as $parsed) {
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
                    $intervals[] = $this->interval($shift);
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
     * Geparseerd blok == bestaande rij? Eerst de weergave: toont de server
     * deze rij exact zo, dan is er niets gewijzigd (ook pauze, aangepaste
     * tijden of een inactief type die de tekst niet kan dragen). Anders de
     * inhoud die de cel wél uitdrukt: soort, unit en shifttype (aangepaste
     * tijden blijven behouden), of bij vrije tijd de tijden. Notitie, pauze
     * en status worden nooit vergeleken; een match behoudt de rij.
     */
    private function blockMatchesRow(RosterCellData $parsed, PlannedShift $row): bool
    {
        if ($this->canonicalDisplay($parsed->raw) === $this->canonicalDisplay($row->displayValue())) {
            return true;
        }
        if ($parsed->kind === RosterCellKind::Invalid) {
            return false;
        }
        if ($row->kind->value !== ($parsed->shiftTypeKind ?? ShiftTypeKind::Work)->value
            || (int) ($row->unit_id ?? 0) !== (int) ($parsed->unitId ?? 0)
        ) {
            return false;
        }
        if ($parsed->shiftTypeId !== null) {
            return (int) ($row->shift_type_id ?? 0) === (int) $parsed->shiftTypeId;
        }

        return ($row->shift_type_id === null || $row->shiftType?->is_active !== true)
            && ShiftType::formatTime($row->start_time) === ShiftType::formatTime($parsed->startTime)
            && ShiftType::formatTime($row->end_time) === ShiftType::formatTime($parsed->endTime);
    }

    private function canonicalDisplay(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s:]+/u', '', $value));
    }

    /**
     * @return array{worker_id: int, date: string, start: ?string, end: ?string, kind: string}
     */
    private function interval(PlannedShift $shift): array
    {
        return [
            'worker_id' => (int) $shift->worker_id,
            'date' => $shift->work_date->toDateString(),
            'start' => $shift->start_time,
            'end' => $shift->end_time,
            'kind' => $shift->kind->value,
        ];
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
