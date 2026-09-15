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

    public function handle(int $tenantId, string $weekStart, ?int $teamId = null, ?User $actor = null, string $period = 'week', bool $includeWeekends = true, ?int $locationId = null): RosterWeekSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $period = $period === 'month' ? 'month' : 'week';
        [$rangeStart, $rangeEnd, $dates] = $this->resolvePeriod->handle($weekStart, $period, $includeWeekends);
        $weekStartDate = $rangeStart->toDateString();
        $weekEndDate = $rangeEnd->toDateString();
        $visibleStart = $dates[0];
        $visibleEnd = $dates[array_key_last($dates)];
        $actorLocationIds = $actor?->accessibleLocationIds();

        $workers = $this->workers($tenantId, $teamId, $actorLocationIds, $weekStartDate, $weekEndDate, $locationId);
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
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->roster_code,
                'name' => $unit->name,
                'location_id' => $unit->location_id,
                'location_name' => $unit->location?->name ?? '',
            ])
            ->unique('id')
            ->values()
            ->all();

        return new RosterWeekSnapshot(
            weekStart: $weekStartDate,
            weekEnd: $weekEndDate,
            dates: $dates,
            dayLabels: $dayLabels,
            workers: $workers->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'name' => $worker->displayName(),
                'team_id' => $worker->internal_team_id,
                'team_name' => $worker->team?->localizedName(),
                'location_ids' => $worker->clocksAllLocations()
                    ? []
                    : $worker->locations->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'clocks_all_locations' => $worker->clocksAllLocations(),
            ])->values()->all(),
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
            units: $units,
        );
    }

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return Collection<int, Worker>
     */
    private function workers(int $tenantId, ?int $teamId, ?array $actorLocationIds, string $weekStart, string $weekEnd, ?int $locationId): Collection
    {
        $query = Worker::query()
            ->with(['team.translations', 'locations'])
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
                $q->whereDoesntHave('locations')
                    ->orWhereHas('locations', fn ($locations) => $locations->whereIn('locations.id', $actorLocationIds));
            });
        }

        if ($locationId !== null) {
            $query->where(function ($q) use ($locationId) {
                $q->whereHas('team', fn ($team) => $team->where('clocks_all_locations', true))
                    ->orWhereDoesntHave('locations')
                    ->orWhereHas('locations', fn ($locations) => $locations->where('locations.id', $locationId));
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
