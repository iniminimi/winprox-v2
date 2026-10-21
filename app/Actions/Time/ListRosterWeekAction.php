<?php

namespace App\Actions\Time;

use App\Data\Time\RosterWeekSnapshot;
use App\Data\Time\WorkerMonthFacts;
use App\Data\Time\WorkerMonthNorm;
use App\Data\Time\WorkerWeekHours;
use App\Enums\MonthNormStatus;
use App\Enums\RosterAttendanceStatus;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Models\BelgianPublicHoliday;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\TenantHolidayAgreement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Support\Time\BelgianHolidayStandardScheme;
use App\Support\Time\TimeModuleAccess;
use App\Support\Time\WorkDurationFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListRosterWeekAction
{
    public function __construct(
        private ResolveRosterPeriodAction $resolvePeriod,
        private ListShiftTypesAction $listShiftTypes,
        private CompareRosterAttendanceAction $compareAttendance,
        private SummarizeWorkerWeekHoursAction $summarizeWeekHours,
        private SummarizeWorkerMonthFactsAction $summarizeMonthFacts,
        private ResolveWorkerMonthNormAction $resolveMonthNorm,
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
        bool $requireScope = false,
    ): RosterWeekSnapshot {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $period = $period === 'month' ? 'month' : 'week';
        [$rangeStart, $rangeEnd, $dates] = $this->resolvePeriod->handle($weekStart, $period, $includeWeekends);
        $weekStartDate = $rangeStart->toDateString();
        $weekEndDate = $rangeEnd->toDateString();
        $visibleStart = $dates[0];
        $visibleEnd = $dates[array_key_last($dates)];
        $actorLocationIds = $actor?->accessibleLocationIds();
        // requireScope: zonder team/locatie-filter geen worker- of cel-werk
        // doen — honderden workers tegelijk laden is traag én onbruikbaar.
        $scoped = ! $requireScope || $teamId !== null || $locationId !== null;

        $workers = $scoped
            ? $this->workers($tenantId, $teamId, $actorLocationIds, $weekStartDate, $weekEndDate, $locationId)
            : collect();
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

        $shifts = $scoped
            ? PlannedShift::query()
                ->with('shiftType')
                ->whereIn('worker_id', $workerIds ?: [0])
                ->whereBetween('work_date', [$visibleStart, $visibleEnd])
                ->orderBy('start_time')
                ->get()
            : collect();

        $cellShifts = [];
        foreach ($shifts as $shift) {
            $key = $shift->worker_id.':'.$shift->work_date->toDateString();
            $activeType = $shift->shiftType !== null && $shift->shiftType->is_active;
            $cellShifts[$key][] = [
                'id' => $shift->id,
                'display' => $shift->displayValue(),
                'code' => $activeType ? $shift->shiftType->code : null,
                'color' => ($activeType ? $shift->shiftType->color : ShiftTypeColor::freeTime())->value,
                'status' => $shift->status->value,
                'kind' => $shift->kind->value,
                'unit_id' => $shift->unit_id !== null ? (int) $shift->unit_id : null,
                'unit_code' => $shift->unit_code,
                'shift_type_id' => $shift->shift_type_id !== null ? (int) $shift->shift_type_id : null,
                'start' => $shift->start_time !== null ? ShiftType::formatTime($shift->start_time) : null,
                'end' => $shift->end_time !== null ? ShiftType::formatTime($shift->end_time) : null,
                'break_minutes' => (int) $shift->break_minutes,
                'description' => $shift->description,
            ];
        }

        // Multi-blok-dag: display = blokken gescheiden door " · " (kopieerbaar;
        // newline zou bij plakken als extra grid-rijen uiteenvallen). Zonder
        // dubbelepunten ("D1 · 1800-2000") zodat het in smalle cellen past.
        // Top-level velden blijven het eerste blok (backwards-compat single-cell).
        $cells = [];
        foreach ($cellShifts as $key => $blocks) {
            $cells[$key] = [
                'multi' => count($blocks) > 1,
                'has_note' => collect($blocks)->contains(
                    fn (array $block) => trim((string) ($block['description'] ?? '')) !== '',
                ),
                'shifts' => $blocks,
                'display' => count($blocks) > 1
                    ? implode(' · ', array_map(
                        fn (array $block) => str_replace(':', '', $block['display']),
                        $blocks,
                    ))
                    : $blocks[0]['display'],
            ] + $blocks[0];
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

        $attendance = $scoped ? $this->compareAttendance->handle(
            $tenantId,
            $shifts,
            $workerIds,
            $dates,
        ) : [];

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

        $weekHours = [];
        $monthFacts = [];
        $monthNorms = [];
        $showsHours = $period === 'week' || $period === 'month';
        // De contractnorm wordt altijd getoond (ook voor superusers) — de
        // planner heeft Norm/Verschil nodig om het rooster te sturen.
        $contractVisible = $showsHours;
        $hoursColumns = $showsHours ? $this->hoursColumns($contractVisible) : [];
        if ($period === 'week' && $workerIds !== []) {
            $weekHours = $this->summarizeWeekHours->handle($tenantId, $workerIds, $weekStartDate);
        }
        if ($period === 'month' && $workerIds !== []) {
            $monthFacts = $this->summarizeMonthFacts->handle($tenantId, $workerIds, $weekStartDate);
            $monthNorms = $this->resolveMonthNorm->handle($tenantId, $workerIds, $weekStartDate);
        }

        // Vaste vrije dagen: enkel weekdagen waar het expliciete patroon
        // 0 minuten vermeldt — ontbrekende/onvolledige patronen markeren
        // nooit een dag als vrij.
        $offWeekdaysByWorker = WorkerWeekdayMinute::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds ?: [0])
            ->where('minutes', 0)
            ->get(['worker_id', 'weekday'])
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->pluck('weekday')->map(fn ($w) => (int) $w)->sort()->values()->all());

        $workerPayload = $workers->map(function (Worker $worker) use ($weekHours, $monthFacts, $monthNorms, $contractVisible, $period, $offWeekdaysByWorker) {
            $payload = [
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
                'off_weekdays' => $offWeekdaysByWorker[$worker->id] ?? [],
            ];

            if ($period === 'week' && isset($weekHours[(int) $worker->id])) {
                $payload['week_hours'] = $this->weekHoursPayload(
                    $weekHours[(int) $worker->id],
                    $contractVisible,
                );
            }

            if ($period === 'month' && isset($monthFacts[(int) $worker->id])) {
                $payload['month_hours'] = $this->monthHoursPayload(
                    $monthFacts[(int) $worker->id],
                    $monthNorms[(int) $worker->id] ?? null,
                    $contractVisible,
                );
            }

            return $payload;
        })->values()->all();

        $rows = $groupMode
            ? $this->buildGroupedRows($workers, $units, $includeUngrouped, $groupUnitIds)
            : array_map(fn (array $worker) => [
                'type' => 'worker',
                'worker_id' => $worker['id'],
            ], $workerPayload);

        $dayFlags = $this->dayFlags($tenantId, $dates, $visibleStart, $visibleEnd);

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
                RosterAttendanceStatus::Gap->value => __('time.schedule.attendance.gap'),
            ],
            locations: $locations,
            units: $unitPayload,
            rows: $rows,
            groupMode: $groupMode,
            hoursColumns: $hoursColumns,
            dayFlags: $dayFlags,
        );
    }

    /**
     * Feestdagen en vervangingsdagen op zichtbare datums (effectieve regeling:
     * opgeslagen tenant-afwijking plus afgeleide Belgische standaard).
     *
     * @param  list<string>  $dates
     * @return array<string, array{kind: string, label: string}>
     */
    private function dayFlags(int $tenantId, array $dates, string $windowStart, string $windowEnd): array
    {
        if ($dates === []) {
            return [];
        }
        $visible = array_fill_keys($dates, true);
        $holidayRows = BelgianPublicHoliday::query()->get(['date', 'code']);
        $holidayDates = [];
        $codeByDate = [];
        foreach ($holidayRows as $holiday) {
            $date = $holiday->date->toDateString();
            $holidayDates[$date] = true;
            $codeByDate[$date] = $holiday->code;
        }
        $effective = BelgianHolidayStandardScheme::effectiveFor(
            TenantHolidayAgreement::query()->where('tenant_id', $tenantId)->get(),
            $holidayRows,
            $holidayDates,
            $windowStart,
            $windowEnd,
        );

        $flags = [];
        foreach ($effective as $agreement) {
            $holidayDate = $agreement->holiday_date->toDateString();
            $code = $codeByDate[$holidayDate] ?? null;
            $name = $code !== null ? __('time.schedule.month_norm.holidays.'.$code) : $holidayDate;
            if (isset($visible[$holidayDate])) {
                $flags[$holidayDate] = [
                    'kind' => 'holiday',
                    'label' => __('time.schedule.day_flag.holiday', [
                        'name' => $name,
                        'effect' => __('time.schedule.month_norm.effects.'.$agreement->effect->value),
                    ]),
                ];
            }
            $replacement = $agreement->replacement_date?->toDateString();
            if ($replacement !== null && isset($visible[$replacement])) {
                $flags[$replacement] = [
                    'kind' => 'replacement',
                    'label' => __('time.schedule.day_flag.replacement', [
                        'name' => $name,
                        'date' => Carbon::parse($holidayDate)->format('d/m/Y'),
                    ]),
                ];
            }
        }

        return $flags;
    }

    /**
     * @return array<string, mixed>
     */
    private function monthHoursPayload(WorkerMonthFacts $facts, ?WorkerMonthNorm $norm, bool $contractVisible): array
    {
        $payload = [
            'planned_minutes' => $facts->plannedMinutes,
            'worked_minutes' => $facts->workedMinutes,
            'registration_incomplete' => $facts->registrationIncomplete,
            'incomplete_blocks' => $facts->incompleteBlocks,
            'absences' => $facts->absences,
        ];

        $status = $norm?->status ?? MonthNormStatus::Incomplete;
        $includeNorm = $contractVisible && $norm?->minutes !== null;

        if ($contractVisible) {
            $payload['norm_status'] = $status->value;
        }
        if ($includeNorm) {
            $payload['contract_minutes'] = $norm->minutes;
            $payload['difference_minutes'] = $facts->plannedMinutes - $norm->minutes;
        }

        $payload['figures'] = $this->hourFigures(
            $contractVisible,
            $includeNorm ? $norm->minutes : null,
            $facts->plannedMinutes,
            $facts->workedMinutes,
            $facts->absences,
            $facts->incompleteBlocks,
            $facts->openRegistrations,
            $facts->oldestOpenClockInAt,
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function weekHoursPayload(WorkerWeekHours $hours, bool $contractVisible): array
    {
        $payload = [
            'planned_minutes' => $hours->plannedMinutes,
            'worked_minutes' => $hours->workedMinutes,
            'registration_incomplete' => $hours->registrationIncomplete,
            'incomplete_blocks' => $hours->incompleteBlocks,
            'absences' => $hours->absences,
            'figures' => $this->hourFigures(
                $contractVisible,
                $contractVisible ? $hours->contractMinutes : null,
                $hours->plannedMinutes,
                $hours->workedMinutes,
                $hours->absences,
                $hours->incompleteBlocks,
                $hours->openRegistrations,
                $hours->oldestOpenClockInAt,
            ),
        ];

        if ($contractVisible) {
            $payload['contract_minutes'] = $hours->contractMinutes;
            $payload['difference_minutes'] = $hours->differenceMinutes();
        }

        return $payload;
    }

    /**
     * @return list<array{key: string, title: string, hint: string, width: int}>
     */
    private function hoursColumns(bool $contractVisible): array
    {
        $columns = [];
        if ($contractVisible) {
            $columns[] = $this->hoursColumn('norm', 46);
            $columns[] = $this->hoursColumn('difference', 52);
        }
        $columns[] = $this->hoursColumn('planned', 46);
        $columns[] = $this->hoursColumn('worked', 46);
        foreach ([ShiftTypeKind::Leave, ShiftTypeKind::Recup, ShiftTypeKind::Sick] as $kind) {
            $columns[] = [
                'key' => $kind->value,
                'title' => __('time.schedule.week_hours.headers.'.$kind->value),
                'hint' => __('time.schedule.types.kinds.'.$kind->value),
                'width' => 44,
            ];
        }
        $columns[] = $this->hoursColumn('attention', 36, __('time.schedule.week_hours.headers.attention_hint'));

        return $columns;
    }

    /**
     * @return array{key: string, title: string, hint: string, width: int}
     */
    private function hoursColumn(string $key, int $width, ?string $hint = null): array
    {
        $title = __('time.schedule.week_hours.headers.'.$key);

        return [
            'key' => $key,
            'title' => $title,
            'hint' => $hint ?? $title,
            'width' => $width,
        ];
    }

    /**
     * @param  array<string, array{days: int, minutes: int}>  $absences
     * @return list<array{value: string, title: string}|null>
     */
    private function hourFigures(
        bool $contractVisible,
        ?int $normMinutes,
        int $plannedMinutes,
        int $workedMinutes,
        array $absences,
        int $incompleteBlocks,
        int $openRegistrations = 0,
        ?Carbon $oldestOpenClockInAt = null,
    ): array {
        $figures = [];
        if ($contractVisible) {
            $figures[] = $normMinutes === null ? null : $this->figure($this->formatMinutes($normMinutes));
            $figures[] = $normMinutes === null
                ? null
                : $this->figure($this->formatSignedMinutes($plannedMinutes - $normMinutes));
        }
        $figures[] = $this->figure($this->formatMinutes($plannedMinutes));
        $figures[] = $this->figure($this->formatMinutes($workedMinutes));
        foreach ([ShiftTypeKind::Leave, ShiftTypeKind::Recup, ShiftTypeKind::Sick] as $kind) {
            $figures[] = $this->figure($this->absenceFigure($absences[$kind->value] ?? null));
        }
        $figures[] = $this->attentionFigure($incompleteBlocks, $openRegistrations, $oldestOpenClockInAt);

        return $figures;
    }

    /**
     * @param  array{days?: int, minutes?: int}|null  $entry
     */
    private function absenceFigure(?array $entry): ?string
    {
        if ($entry === null) {
            return null;
        }
        $parts = [];
        $days = (int) ($entry['days'] ?? 0);
        $minutes = (int) ($entry['minutes'] ?? 0);
        if ($days > 0) {
            $parts[] = (string) $days;
        }
        if ($minutes > 0) {
            $parts[] = $this->formatMinutes($minutes);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return array{value: string, title: string}|null
     */
    private function attentionFigure(int $incompleteBlocks, int $openRegistrations, ?Carbon $oldestOpenClockInAt): ?array
    {
        if ($incompleteBlocks < 1 && $openRegistrations < 1) {
            return null;
        }
        $parts = [];
        $titles = [];
        if ($incompleteBlocks > 0) {
            $parts[] = (string) $incompleteBlocks;
            $titles[] = trans_choice('time.schedule.week_hours.incomplete_blocks', $incompleteBlocks, [
                'count' => $incompleteBlocks,
            ]);
        }
        if ($openRegistrations > 0) {
            $parts[] = '!';
            $titles[] = trans_choice('time.schedule.week_hours.open_registration', $openRegistrations, [
                'count' => $openRegistrations,
                'datetime' => $oldestOpenClockInAt?->setTimezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '',
            ]);
        }

        return [
            'value' => implode(' · ', $parts),
            'title' => implode(' · ', $titles),
        ];
    }

    /**
     * @return array{value: string, title: string}|null
     */
    private function figure(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return [
            'value' => $value,
            'title' => $value,
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '0';
        }

        return WorkDurationFormatter::compact($minutes);
    }

    private function formatSignedMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '0';
        }

        $sign = $minutes > 0 ? '+' : '−';

        return $sign.$this->formatMinutes(abs($minutes));
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
            // Locatie-uurrooster = alleen toegewezen workers. Vlinders/invallers
            // (lege locaties of clocks_all_locations) blijven inklokken overal, maar
            // horen niet in dit gefilterde rooster.
            $query->whereHas(
                'locations',
                fn ($locations) => $locations->where('locations.id', $locationId),
            );
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
