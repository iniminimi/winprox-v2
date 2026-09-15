<?php

namespace App\Actions\Time;

use App\Data\Time\RosterWeekSnapshot;
use App\Enums\ShiftTypeColor;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListRosterWeekAction
{
    public function __construct(
        private ResolveRosterWeekAction $resolveWeek,
        private ListShiftTypesAction $listShiftTypes,
    ) {}

    public function handle(int $tenantId, string $weekStart, ?int $teamId = null, ?User $actor = null): RosterWeekSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        [$monday, , $dates] = $this->resolveWeek->handle($weekStart);
        $weekStartDate = $monday->toDateString();
        $weekEndDate = $dates[6];

        $workers = $this->workers($tenantId, $teamId, $actor, $weekStartDate, $weekEndDate);
        $workerIds = $workers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $shifts = PlannedShift::query()
            ->with('shiftType')
            ->whereIn('worker_id', $workerIds ?: [0])
            ->whereBetween('work_date', [$weekStartDate, $weekEndDate])
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
                'start' => ShiftType::formatTime($shift->start_time),
                'end' => ShiftType::formatTime($shift->end_time),
            ];
        }

        $types = $this->listShiftTypes->handle($tenantId, false);
        $typePayload = array_map(fn (ShiftType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'label' => $type->label,
            'start' => ShiftType::formatTime($type->start_time),
            'end' => ShiftType::formatTime($type->end_time),
            'break_minutes' => $type->break_minutes,
            'color' => $type->color->value,
            'active' => $type->is_active,
        ], $types);

        $weekPublished = $shifts->contains(fn (PlannedShift $shift) => $shift->status->isPublished());

        $dayLabels = array_map(
            fn (string $date) => Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('D d/m'),
            $dates,
        );

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
            ])->values()->all(),
            cells: $cells,
            types: $typePayload,
            teams: $teams,
            weekPublished: $weekPublished,
            nightShifts: false,
        );
    }

    /**
     * @return Collection<int, Worker>
     */
    private function workers(int $tenantId, ?int $teamId, ?User $actor, string $weekStart, string $weekEnd): Collection
    {
        $locationIds = $actor?->accessibleLocationIds();

        $query = Worker::query()
            ->with('team.translations')
            ->where('tenant_id', $tenantId)
            ->when($teamId !== null, fn ($q) => $q->where('internal_team_id', $teamId))
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->where('is_active', true)
                    ->orWhereHas('plannedShifts', function ($shifts) use ($weekStart, $weekEnd) {
                        $shifts->whereBetween('work_date', [$weekStart, $weekEnd]);
                    });
            });

        if ($locationIds !== null) {
            $query->where(function ($q) use ($locationIds) {
                $q->whereDoesntHave('locations')
                    ->orWhereHas('locations', fn ($locations) => $locations->whereIn('locations.id', $locationIds));
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
