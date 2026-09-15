<?php

namespace App\Actions\Time;

use App\Data\Time\RosterWeekSnapshot;
use App\Enums\RosterAttendanceStatus;
use App\Enums\ShiftTypeColor;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListRosterWeekAction
{
    public function __construct(
        private ResolveRosterPeriodAction $resolvePeriod,
        private ListShiftTypesAction $listShiftTypes,
        private CompareRosterAttendanceAction $compareAttendance,
    ) {}

    /**
     * @param  list<int>|null  $groupUnitIds  null = no group filter (all); list = selected unit ids
     */
    public function handle(
        int $tenantId,
        string $weekStart,
        ?int $teamId = null,
        ?User $actor = null,
        string $period = 'week',
        bool $includeWeekends = true,
        ?int $locationId = null,
        ?array $groupUnitIds = null,
        bool $includeUngrouped = true,
    ): RosterWeekSnapshot {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $period = $period === 'month' ? 'month' : 'week';
        [$rangeStart, $rangeEnd, $dates] = $this->resolvePeriod->handle($weekStart, $period, $includeWeekends);
        $weekStartDate = $rangeStart->toDateString();
        $weekEndDate = $rangeEnd->toDateString();
        $visibleStart = $dates[0];
        $visibleEnd = $dates[array_key_last($dates)];
        $actorLocationIds = $actor?->accessibleLocationIds();

        $workers = $this->workers($tenantId, $teamId, $actorLocationIds, $weekStartDate, $weekEndDate, $locationId);
        $groupMode = $locationId !== null;

        $units = Unit::query()
            ->with(['location:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('location_id', $actorLocationIds ?: [0]))
            ->orderBy('roster_code')
            ->get(['id', 'name', 'roster_code', 'location_id'])
            ->unique('id')
            ->values();

        $unitPayload = $units->map(fn (Unit $unit) => [
            'id' => $unit->id,
            'code' => $unit->roster_code,
            'name' => $unit->name,
            'location_id' => $unit->location_id,
            'location_name' => $unit->location?->name ?? '',
        ])->values()->all();

        if ($groupMode) {
            $allowedUnitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
            $selectedUnitIds = $groupUnitIds === null
                ? $allowedUnitIds
                : array_values(array_intersect(
                    array_map('intval', $groupUnitIds),
                    $allowedUnitIds,
                ));

            $workers = $workers->filter(function (Worker $worker) use ($selectedUnitIds, $includeUngrouped) {
                $defaultId = $worker->default_unit_id !== null ? (int) $worker->default_unit_id : null;
                if ($defaultId === null) {
                    return $includeUngrouped;
                }

                return in_array($defaultId, $selectedUnitIds, true);
            })->values();

            $workers = $this->sortWorkersForGroups($workers);
        }

        $workerIds = $workers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $shifts = PlannedShift::query()
            ->with('shiftType')
            ->whereIn('worker_id', $workerIds ?: [0])
            ->whereBetween('work_date', [$visibleStart, $visibleEnd])
            ->orderBy('start_time')
            ->get();

        $cells = [];
        foreach ($shifts as $shift) {
            $key = $shift->worker_id.':'.$shift->work_date->toDateString();
            $activeType = $shift->shiftType !== null && $shift->shiftType->is_active;
            $cells[$key] = [
                'id' => $shift->id,
                'display' => $shift->displayValue(),
                'code' => $activeType ? $shift->shiftType->code : null,
                'color' => ($activeType ? $shift->shiftType->color : ShiftTypeColor::freeTime())->value,
                'status' => $shift->status->value,
                'kind' => $shift->kind->value,
                'unit_code' => $shift->unit_code,
                'start' => $shift->start_time !== null ? ShiftType::formatTime($shift->start_time) : null,
                'end' => $shift->end_time !== null ? ShiftType::formatTime($shift->end_time) : null,
            ];
        }

        $types = $this->listShiftTypes->handle($tenantId, false);
        $typePayload = array_map(fn (ShiftType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'label' => $type->label,
            'kind' => $type->kind->value,
            'start' => $type->start_time !== null ? ShiftType::formatTime($type->start_time) : null,
            'end' => $type->end_time !== null ? ShiftType::formatTime($type->end_time) : null,
            'break_minutes' => $type->break_minutes,
            'color' => $type->color->value,
            'active' => $type->is_active,
        ], $types);

        $attendance = $this->compareAttendance->handle(
            $tenantId,
            $shifts,
            $workerIds,
            $dates,
        );

        $weekPublished = $shifts->contains(fn (PlannedShift $shift) => $shift->status->isPublished());

        $locale = app()->getLocale();
        $dayLabels = array_map(
            fn (string $date) => $period === 'month'
                ? Carbon::parse($date)->locale($locale)->isoFormat('dd')
                : Carbon::parse($date)->locale($locale)->translatedFormat('D d/m'),
            $dates,
        );
        $dayNumbers = array_map(
            fn (string $date) => (int) Carbon::parse($date)->day,
            $dates,
        );
        $monthLabel = $period === 'month'
            ? $rangeStart->locale($locale)->translatedFormat('F Y')
            : '';

        $teams = InternalTeam::query()
            ->with('translations')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (InternalTeam $team) => [
                'id' => $team->id,
                'name' => $team->localizedName(),
            ])
            ->values()
            ->all();

        $locations = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('id', $actorLocationIds ?: [0]))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->values()
            ->all();

        $workerPayload = $workers->map(fn (Worker $worker) => [
            'id' => $worker->id,
            'name' => $worker->displayName(),
            'team_id' => $worker->internal_team_id,
            'team_name' => $worker->team?->localizedName(),
            'location_ids' => $worker->clocksAllLocations()
                ? []
                : $worker->locations->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'clocks_all_locations' => $worker->clocksAllLocations(),
            'default_unit_id' => $worker->default_unit_id !== null ? (int) $worker->default_unit_id : null,
            'group_code' => $worker->defaultUnit?->roster_code,
            'group_name' => $worker->defaultUnit?->name,
        ])->values()->all();

        $rows = $groupMode
            ? $this->buildGroupedRows($workers, $units, $includeUngrouped, $groupUnitIds)
            : array_map(fn (array $worker) => [
                'type' => 'worker',
                'worker_id' => $worker['id'],
            ], $workerPayload);

        return new RosterWeekSnapshot(
            weekStart: $weekStartDate,
            weekEnd: $weekEndDate,
            dates: $dates,
            dayLabels: $dayLabels,
            workers: $workerPayload,
            cells: $cells,
            types: $typePayload,
            teams: $teams,
            weekPublished: $weekPublished,
            nightShifts: false,
            period: $period,
            monthLabel: $monthLabel,
            dayNumbers: $dayNumbers,
            attendance: $attendance,
            attendanceMessages: [
                RosterAttendanceStatus::Missing->value => __('time.schedule.attendance.missing'),
                RosterAttendanceStatus::Deviation->value => __('time.schedule.attendance.deviation'),
                RosterAttendanceStatus::Unplanned->value => __('time.schedule.attendance.unplanned'),
                RosterAttendanceStatus::Ok->value => __('time.schedule.attendance.ok'),
            ],
            locations: $locations,
            units: $unitPayload,
            rows: $rows,
            groupMode: $groupMode,
        );
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @param  Collection<int, Unit>  $units
     * @param  list<int>|null  $groupUnitIds
     * @return list<array<string, mixed>>
     */
    private function buildGroupedRows(Collection $workers, Collection $units, bool $includeUngrouped, ?array $groupUnitIds): array
    {
        $allowedUnitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedUnitIds = $groupUnitIds === null
            ? $allowedUnitIds
            : array_values(array_intersect(array_map('intval', $groupUnitIds), $allowedUnitIds));

        $byUnit = $workers->groupBy(fn (Worker $worker) => $worker->default_unit_id !== null ? (int) $worker->default_unit_id : 0);
        $rows = [];

        foreach ($units as $unit) {
            $unitId = (int) $unit->id;
            if (! in_array($unitId, $selectedUnitIds, true)) {
                continue;
            }
            $groupWorkers = $this->sortWorkersByName($byUnit->get($unitId, collect()));
            if ($groupWorkers->isEmpty()) {
                continue;
            }
            $rows[] = [
                'type' => 'section',
                'label' => trim($unit->roster_code.' · '.$unit->name),
                'unit_id' => $unitId,
            ];
            foreach ($groupWorkers as $worker) {
                $rows[] = [
                    'type' => 'worker',
                    'worker_id' => (int) $worker->id,
                ];
            }
        }

        if ($includeUngrouped) {
            $ungrouped = $this->sortWorkersByName($byUnit->get(0, collect()));
            if ($ungrouped->isNotEmpty()) {
                $rows[] = [
                    'type' => 'section',
                    'label' => __('time.schedule.group_ungrouped'),
                    'unit_id' => null,
                ];
                foreach ($ungrouped as $worker) {
                    $rows[] = [
                        'type' => 'worker',
                        'worker_id' => (int) $worker->id,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @return Collection<int, Worker>
     */
    private function sortWorkersForGroups(Collection $workers): Collection
    {
        return $workers->sortBy(
            fn (Worker $worker) => sprintf(
                '%s\0%s\0%s',
                mb_strtolower($worker->defaultUnit?->roster_code ?? 'ÿÿÿ'),
                mb_strtolower((string) $worker->first_name),
                mb_strtolower((string) $worker->last_name),
            ),
        )->values();
    }

    /**
     * @param  Collection<int, Worker>  $workers
     * @return Collection<int, Worker>
     */
    private function sortWorkersByName(Collection $workers): Collection
    {
        return $workers->sortBy(
            fn (Worker $worker) => sprintf(
                '%s\0%s',
                mb_strtolower((string) $worker->first_name),
                mb_strtolower((string) $worker->last_name),
            ),
        )->values();
    }

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return Collection<int, Worker>
     */
    private function workers(int $tenantId, ?int $teamId, ?array $actorLocationIds, string $weekStart, string $weekEnd, ?int $locationId): Collection
    {
        $query = Worker::query()
            ->with(['team.translations', 'locations', 'defaultUnit'])
            ->where('tenant_id', $tenantId)
            ->when($teamId !== null, fn ($q) => $q->where('internal_team_id', $teamId))
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->where('is_active', true)
                    ->orWhereHas('plannedShifts', function ($shifts) use ($weekStart, $weekEnd) {
                        $shifts->whereBetween('work_date', [$weekStart, $weekEnd]);
                    });
            });

        if ($actorLocationIds !== null) {
            $query->where(function ($q) use ($actorLocationIds) {
                $q->whereHas('team', fn ($team) => $team->where('clocks_all_locations', true))
                    ->orWhereHas('locations', fn ($locations) => $locations->whereIn('locations.id', $actorLocationIds ?: [0]));
            });
        }

        if ($locationId !== null) {
            $query->where(function ($q) use ($locationId) {
                $q->whereHas('team', fn ($team) => $team->where('clocks_all_locations', true))
                    ->orWhereHas('locations', fn ($locations) => $locations->where('locations.id', $locationId));
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
