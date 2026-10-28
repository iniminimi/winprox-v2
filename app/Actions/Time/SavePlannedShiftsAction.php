<?php

namespace App\Actions\Time;

use App\Data\Time\RosterCellData;
use App\Data\Time\SavePlannedShiftsData;
use App\Enums\PlannedShiftStatus;
use App\Enums\RosterCellKind;
use App\Enums\RosterRestorePointSource;
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
        private RecordRosterRestorePointAction $recordRestorePoint,
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

        // Parsen hangt alleen af van de celtekst en de al geladen types/units,
        // niet van de vergrendelde rijen. De vergelijking, de vergrendelde
        // dagen en de overlap blijven in de transactie: een token dat op de
        // rij zelf overeenkomt (ook een inactief type) mag de cel niet
        // ongeldig maken, en een fout daarna mag niets vastleggen.
        $parsedByIndex = [];
        foreach ($data->cells as $index => $cell) {
            if (! empty($cell['keep'])) {
                continue;
            }
            $worker = $workers->get((int) $cell['worker_id']);
            $catalog = $units->filter(
                fn (Unit $unit) => $worker instanceof Worker && $worker->canClockAt((int) $unit->location_id),
            );
            $parsedByIndex[$index] = $this->parseCell->handleAll((string) $cell['raw'], $types, $catalog);
        }

        return DB::transaction(function () use ($tenant, $data, $workerIds, $weekStart, $weekEnd, $actorUserId, $dates, $dateSet, $parsedByIndex) {
            // Alleen rijen op zichtbare datums horen bij deze save. In een
            // maand zonder weekends liggen weekenddagen binnen [start, eind]
            // maar buiten de grid: die rijen worden niet verwijderd en niet
            // als vergrendelde wijziging gezien. start_time, id is een vaste
            // lockvolgorde; de koppeling sorteert daarna nog eens zelf.
            $existing = PlannedShift::query()
                ->with('shiftType')
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->orderBy('start_time')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (PlannedShift $shift) => isset($dateSet[$shift->work_date->toDateString()]))
                ->values();

            // Alleen een nieuw blok wordt draft. Een blok dat overeenkomt
            // houdt zijn status tot de beheerder de vervanging publiceert.
            $status = PlannedShiftStatus::Draft;

            $existingByDay = $existing->groupBy(
                fn (PlannedShift $shift) => $shift->worker_id.':'.$shift->work_date->toDateString(),
            );

            // Per cel eerst de unieke één-op-één-matches, ook als de
            // tekstvolgorde kruist. De rest volgt de grootste koppeling die
            // niet kruist. Historische dagen (< vandaag, app-tijdzone) zijn
            // vergrendeld: elke werkelijke wijziging — wijzigen, legen of op
            // een lege dag plannen — wordt als cel geflagd.
            $preservedIds = [];
            $invalid = [];
            $lockedErrors = [];
            $pending = [];
            foreach ($data->cells as $index => $cell) {
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

                [$kept, $new, $error] = $this->pairBlocks($rows, $parsedByIndex[$index]);

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
                if ($new === [] && count($kept) === $rows->count()) {
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

            if ($pending !== []) {
                $scope = [];
                foreach ($pending as $item) {
                    $scope[] = [
                        'worker_id' => (int) $item['cell']['worker_id'],
                        'date' => (string) $item['cell']['date'],
                    ];
                }
                $this->recordRestorePoint->handle(
                    $tenant,
                    $actorUserId,
                    RosterRestorePointSource::Grid,
                    $scope,
                    $this->recordRestorePoint->capture($existing),
                );
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
     * Eerst unieke één-op-één-matches, ook als de tekstvolgorde kruist.
     * Daarna de grootste koppeling die niet kruist op wat overblijft.
     * Een gekoppelde rij wordt niet herschreven: id, notitie, pauze, status
     * en aangepaste tijden blijven. Alleen een ongekoppeld token wordt een
     * nieuw concept; alleen een ongekoppelde rij verdwijnt.
     *
     * Bij twee even grote koppelingen in de rest blijft de vroegste rij
     * behouden. Identieke codes zijn niet uniek en vallen daarop terug.
     * De tekst `V · V` onderscheidt hun volgorde niet en wordt niet als
     * omwisseling gelezen.
     *
     * @param  Collection<int, PlannedShift>  $rows
     * @param  list<RosterCellData>  $parsedBlocks
     * @return array{0: list<PlannedShift>, 1: list<RosterCellData>, 2: ?RosterCellData}
     */
    private function pairBlocks(Collection $rows, array $parsedBlocks): array
    {
        $sorted = $rows->sort(function (PlannedShift $a, PlannedShift $b): int {
            $byTime = $this->rosterMinutes($a) <=> $this->rosterMinutes($b);

            return $byTime !== 0 ? $byTime : ((int) $a->id <=> (int) $b->id);
        })->values();

        $blocks = [];
        foreach ($parsedBlocks as $parsed) {
            if ($parsed->isEmpty()) {
                continue;
            }
            $blocks[] = $parsed;
        }

        $tokenCount = count($blocks);
        $rowCount = $sorted->count();
        $tokenHits = array_fill(0, $tokenCount, []);
        $rowHits = array_fill(0, $rowCount, []);
        for ($token = 0; $token < $tokenCount; $token++) {
            for ($row = 0; $row < $rowCount; $row++) {
                if (! $this->blockMatchesRow($blocks[$token], $sorted[$row])) {
                    continue;
                }
                $tokenHits[$token][] = $row;
                $rowHits[$row][] = $token;
            }
        }

        $lockedToken = array_fill(0, $tokenCount, false);
        $lockedRow = array_fill(0, $rowCount, false);
        $kept = [];
        for ($token = 0; $token < $tokenCount; $token++) {
            if (count($tokenHits[$token]) !== 1) {
                continue;
            }
            $row = $tokenHits[$token][0];
            if (count($rowHits[$row]) !== 1) {
                continue;
            }
            $lockedToken[$token] = true;
            $lockedRow[$row] = true;
            $kept[] = $sorted[$row];
        }

        $restBlocks = [];
        $restRows = [];
        for ($token = 0; $token < $tokenCount; $token++) {
            if (! $lockedToken[$token]) {
                $restBlocks[] = $blocks[$token];
            }
        }
        for ($row = 0; $row < $rowCount; $row++) {
            if (! $lockedRow[$row]) {
                $restRows[] = $sorted[$row];
            }
        }

        [$restKept, $new, $error] = $this->alignNonCrossing($restBlocks, $restRows);
        if ($error !== null) {
            return [[], [], $error];
        }

        return [array_merge($kept, $restKept), $new, null];
    }

    /**
     * Grootste koppeling die niet kruist. Bij gelijke score blijft de
     * vroegste rij: een token dat de huidige rij niet raakt wordt een nieuw
     * blok zolang dat de score niet verlaagt, anders vervalt de rij.
     *
     * @param  list<RosterCellData>  $blocks
     * @param  list<PlannedShift>  $rows
     * @return array{0: list<PlannedShift>, 1: list<RosterCellData>, 2: ?RosterCellData}
     */
    private function alignNonCrossing(array $blocks, array $rows): array
    {
        $tokenCount = count($blocks);
        $rowCount = count($rows);
        $score = array_fill(0, $tokenCount + 1, array_fill(0, $rowCount + 1, 0));
        $matches = array_fill(0, $tokenCount, array_fill(0, $rowCount, false));

        for ($token = $tokenCount - 1; $token >= 0; $token--) {
            for ($row = $rowCount - 1; $row >= 0; $row--) {
                $matches[$token][$row] = $this->blockMatchesRow($blocks[$token], $rows[$row]);
                $best = $score[$token + 1][$row];
                $best = max($best, $score[$token][$row + 1]);
                if ($matches[$token][$row]) {
                    $best = max($best, $score[$token + 1][$row + 1] + 1);
                }
                $score[$token][$row] = $best;
            }
        }

        $kept = [];
        $new = [];
        $token = 0;
        $row = 0;
        while ($token < $tokenCount && $row < $rowCount) {
            if ($matches[$token][$row] && $score[$token][$row] === $score[$token + 1][$row + 1] + 1) {
                $kept[] = $rows[$row];
                $token++;
                $row++;

                continue;
            }

            $skipIsOptimal = $score[$token][$row] === $score[$token + 1][$row];
            $dropIsOptimal = $score[$token][$row] === $score[$token][$row + 1];
            if ($blocks[$token]->kind === RosterCellKind::Invalid) {
                if ($dropIsOptimal) {
                    $row++;

                    continue;
                }

                return [[], [], $blocks[$token]];
            }
            if ($skipIsOptimal) {
                $new[] = $blocks[$token];
                $token++;

                continue;
            }
            $row++;
        }

        while ($token < $tokenCount) {
            if ($blocks[$token]->kind === RosterCellKind::Invalid) {
                return [[], [], $blocks[$token]];
            }
            $new[] = $blocks[$token];
            $token++;
        }

        return [$kept, $new, null];
    }

    private function rosterMinutes(PlannedShift $row): int
    {
        $time = $row->start_time;
        if (! is_string($time) || $time === '') {
            return -1;
        }

        return ShiftType::timeToMinutes($time);
    }

    /**
     * Vergelijkingsvelden, in deze volgorde en niet anders: de canonieke
     * weergave; anders soort én unit; anders het shifttype-id, waarbij de
     * opgeslagen tijden blijven staan; anders bij een vrije tijd exact het
     * begin- en einduur. `13:00-15:00` raakt `12:00-14:00` dus niet en wordt
     * een nieuw blok. Notitie, pauze en status worden niet vergeleken; een
     * match behoudt de rij ongewijzigd.
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
