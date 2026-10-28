<?php

namespace App\Livewire\Time;

use App\Actions\Time\CopyRosterWeeksAction;
use App\Actions\Time\CorrectPlannedShiftAction;
use App\Actions\Time\ListLocationStaffingShortfallsAction;
use App\Actions\Time\ListRosterRestorePointsAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\LoadRosterDayEditorAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ReplacePlannedShiftAction;
use App\Actions\Time\ResolveRosterPeriodAction;
use App\Actions\Time\RestoreRosterPointAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SuggestReplacementAction;
use App\Data\Time\CopyRosterWeeksData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\RosterRestoreAssessment;
use App\Data\Time\RosterWeekSnapshot;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Enums\PlannedShiftStatus;
use App\Exceptions\RosterRestoreConflictException;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\CopyWeekRequest;
use App\Http\Requests\Time\PublishWeekRequest;
use App\Http\Requests\Time\RestoreRosterPointRequest;
use App\Http\Requests\Time\SavePlannedDayRequest;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\PlannedShift;
use App\Models\RosterRestorePoint;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenancy;
use App\Support\Time\RosterHistoryLock;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class RosterIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    private const VIEW_COOKIE = 'roster_view';

    private const VIEW_COOKIE_MINUTES = 60 * 24 * 365;

    private const SCOPE_COOKIE = 'roster_scope';

    private const SCOPE_COOKIE_MINUTES = 60 * 24 * 365;

    private const STATS_COOKIE = 'roster_stats';

    private const STATS_COOKIE_MINUTES = 60 * 24 * 365;

    #[Url(as: 'week')]
    public string $weekStart = '';

    #[Url(as: 'view')]
    public string $view = 'week';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'weekends')]
    public bool $showWeekends = false;

    #[Url(as: 'stats')]
    public bool $showStats = true;

    /** @var list<int> */
    public array $groupUnitIds = [];

    public bool $showUngrouped = true;

    public bool $groupsReady = false;

    public bool $showLegendModal = false;

    public bool $showUnsavedLeaveModal = false;

    public bool $showDayEditor = false;

    /** @var array<string, mixed> */
    public array $dayEditorContext = [];

    /** @var list<array<string, mixed>> */
    public array $dayEditorBlocks = [];

    public string $dayEditorError = '';

    public bool $dayEditorLocked = false;

    public bool $showCorrectionModal = false;

    public ?int $correctionShiftId = null;

    public bool $correctionIsNew = false;

    /** @var array<string, mixed> */
    public array $correctionBlock = [];

    public string $correctionReason = '';

    public string $correctionError = '';

    public bool $showReplacementModal = false;

    /** @var list<int> */
    public array $replacementBlockIds = [];

    /** @var list<array{worker_id: int, name: string, tier: int, unit_code: ?string}> */
    public array $replacementCandidates = [];

    /** @var list<string> blok-labels voor de modal-kop */
    public array $replacementBlockLabels = [];

    public ?int $replacementWorkerId = null;

    public ?int $replacementAbsenceTypeId = null;

    public string $replacementError = '';

    public bool $showCopyModal = false;

    public string $copyTargetWeek = '';

    public int $copyWeekCount = 1;

    /** @var list<array<string, mixed>> */
    public array $copyReport = [];

    public string $copyError = '';

    public bool $showRestoreModal = false;

    /** @var list<array<string, mixed>> */
    public array $restorePoints = [];

    public bool $restorePointsCapped = false;

    public ?int $restorePointId = null;

    /** @var array<string, mixed> */
    public array $restoreDetail = [];

    public string $restoreError = '';

    public bool $restoreDone = false;

    public ?int $restoreUndoPointId = null;

    public function boot(): void
    {
        abort_unless(TimeModuleAccess::rosterEnabledForTenantId((int) Tenancy::id()), 403);
    }

    public function mount(ResolveRosterPeriodAction $resolvePeriod): void
    {
        $this->authorize('viewAny', PlannedShift::class);
        $this->normalizeView();
        $this->restoreRememberedView();
        $this->restoreRememberedScope();
        $this->restoreRememberedStats();

        if ($this->weekStart === '') {
            $this->weekStart = $this->isMonth()
                ? now()->startOfMonth()->toDateString()
                : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            [$start] = $resolvePeriod->handle($this->weekStart, $this->period());
            $this->weekStart = $start->toDateString();
        }

        $this->syncGroupFiltersFromLocation();
    }

    public function openLegendModal(): void
    {
        $this->showLegendModal = true;
    }

    public function closeLegendModal(): void
    {
        $this->showLegendModal = false;
    }

    public function openUnsavedLeaveModal(): void
    {
        $this->showUnsavedLeaveModal = true;
    }

    public function closeUnsavedLeaveModal(): void
    {
        $this->showUnsavedLeaveModal = false;
    }

    public function openDayEditor(int $workerId, string $date, LoadRosterDayEditorAction $load): void
    {
        $this->authorize('update', PlannedShift::class);
        $this->dayEditorContext = $load->handle(
            (int) Tenancy::id(),
            $workerId,
            $date,
            auth()->user()?->accessibleLocationIds(),
        );
        $this->dayEditorBlocks = array_map(fn (array $block) => [
            'id' => $block['id'],
            'shift_type_id' => $block['shift_type_id'],
            'start_time' => $block['start_time'],
            'end_time' => $block['end_time'],
            'break_minutes' => $block['break_minutes'],
            'unit_id' => $block['unit_id'],
            'description' => $block['description'] ?? '',
        ], $this->dayEditorContext['blocks']);
        $this->dayEditorLocked = RosterHistoryLock::isLockedDate(
            Carbon::parse($date)->toDateString(),
        );
        // Op een vergrendelde dag is de editor readonly — geen leeg
        // seed-blok tonen alsof er gepland kan worden.
        if ($this->dayEditorBlocks === [] && ! $this->dayEditorLocked) {
            $this->dayEditorBlocks[] = $this->emptyDayEditorBlock();
        }
        $this->dayEditorError = '';
        $this->showDayEditor = true;
    }

    public function closeDayEditor(): void
    {
        $this->showDayEditor = false;
        $this->dayEditorLocked = false;
        $this->dayEditorContext = [];
        $this->dayEditorBlocks = [];
        $this->dayEditorError = '';
    }

    public function addDayEditorBlock(): void
    {
        $this->dayEditorBlocks[] = $this->emptyDayEditorBlock();
    }

    public function removeDayEditorBlock(int $index): void
    {
        unset($this->dayEditorBlocks[$index]);
        $this->dayEditorBlocks = array_values($this->dayEditorBlocks);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEditorTime(mixed $value): ?string
    {
        $time = trim((string) ($value ?? ''));
        if ($time === '') {
            return null;
        }
        // "0800"/"800" → "8:00"; "8:00" → "08:00".
        $time = preg_replace('/^(\d{1,2})(\d{2})$/', '\1:\2', $time) ?? $time;

        return preg_replace('/^(\d):/', '0\1:', $time) ?? $time;
    }

    private function emptyDayEditorBlock(): array
    {
        return [
            'id' => null,
            'shift_type_id' => null,
            'start_time' => '',
            'end_time' => '',
            'break_minutes' => 0,
            'unit_id' => null,
            'description' => '',
        ];
    }

    public function saveDayEditor(SavePlannedDayAction $save): void
    {
        $this->authorize('update', PlannedShift::class);

        $workerId = (int) ($this->dayEditorContext['worker']['id'] ?? 0);
        $date = (string) ($this->dayEditorContext['date'] ?? '');

        $blocks = array_map(fn (array $block) => [
            'id' => $block['id'] !== null && $block['id'] !== '' ? (int) $block['id'] : null,
            'shift_type_id' => $block['shift_type_id'] !== null && $block['shift_type_id'] !== '' ? (int) $block['shift_type_id'] : null,
            'start_time' => $this->normalizeEditorTime($block['start_time'] ?? null),
            'end_time' => $this->normalizeEditorTime($block['end_time'] ?? null),
            'break_minutes' => (int) ($block['break_minutes'] ?? 0),
            'unit_id' => $block['unit_id'] !== null && $block['unit_id'] !== '' ? (int) $block['unit_id'] : null,
            'description' => $block['description'] ?? null,
        ], $this->dayEditorBlocks);

        try {
            Validator::make(
                ['worker_id' => $workerId, 'date' => $date, 'blocks' => $blocks],
                SavePlannedDayRequest::rulesFor(),
            )->validate();
        } catch (ValidationException) {
            $this->dayEditorError = __('time.schedule.errors.invalid_time');

            return;
        }

        try {
            $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedDayData($workerId, $date, $blocks),
                auth()->id(),
                auth()->user()?->accessibleLocationIds(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->dayEditorError = __($e->getMessage());

            return;
        }

        $this->closeDayEditor();
        session()->flash('time_flash', __('time.schedule.saved'));
        $this->dispatch('roster-week-changed');
    }

    /**
     * @param  list<int>  $blockIds  werkblok-id's van dezelfde worker×dag
     */
    public function openReplacement(array $blockIds, SuggestReplacementAction $suggest): void
    {
        $this->authorize('update', PlannedShift::class);
        $this->replacementError = '';
        $ids = array_values(array_unique(array_map('intval', $blockIds)));

        try {
            $this->replacementCandidates = $suggest->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                $ids,
            );
        } catch (InvalidArgumentException $e) {
            $this->replacementError = __($e->getMessage());

            return;
        }

        $this->replacementBlockIds = $ids;
        $this->replacementBlockLabels = collect($this->dayEditorContext['blocks'] ?? [])
            ->filter(fn (array $block) => in_array((int) $block['id'], $ids, true))
            ->pluck('display')
            ->all();
        $this->replacementWorkerId = null;

        $absenceTypes = collect($this->dayEditorContext['types'] ?? [])
            ->filter(fn (array $type) => ($type['kind'] ?? 'work') !== 'work')
            ->values();
        $preferred = $absenceTypes->firstWhere('kind', 'sick') ?? $absenceTypes->first();
        $this->replacementAbsenceTypeId = $preferred !== null ? (int) $preferred['id'] : null;

        $this->showReplacementModal = true;
    }

    public function closeReplacement(): void
    {
        $this->reset([
            'showReplacementModal',
            'replacementBlockIds',
            'replacementCandidates',
            'replacementBlockLabels',
            'replacementWorkerId',
            'replacementAbsenceTypeId',
            'replacementError',
        ]);
    }

    public function confirmReplacement(ReplacePlannedShiftAction $replace, LoadRosterDayEditorAction $load): void
    {
        $this->authorize('update', PlannedShift::class);

        if ($this->replacementWorkerId === null || $this->replacementAbsenceTypeId === null) {
            $this->replacementError = __('time.schedule.errors.replacement_not_eligible');

            return;
        }

        try {
            $replace->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                $this->replacementBlockIds,
                $this->replacementWorkerId,
                $this->replacementAbsenceTypeId,
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->replacementError = __($e->getMessage());

            return;
        }

        $workerId = (int) ($this->dayEditorContext['worker']['id'] ?? 0);
        $date = (string) ($this->dayEditorContext['date'] ?? '');
        $this->closeReplacement();
        if ($workerId !== 0 && $date !== '') {
            $this->openDayEditor($workerId, $date, $load);
        }
        session()->flash('time_flash', __('time.schedule.replacement.done'));
        $this->dispatch('roster-week-changed');
    }

    public function openCorrection(int $shiftId): void
    {
        $this->authorize('correct', PlannedShift::class);

        $block = collect($this->dayEditorContext['blocks'] ?? [])
            ->first(fn (array $candidate) => (int) $candidate['id'] === $shiftId);
        if ($block === null) {
            return;
        }

        $this->correctionShiftId = $shiftId;
        $this->correctionIsNew = false;
        $this->correctionBlock = [
            'work_date' => (string) ($this->dayEditorContext['date'] ?? ''),
            'shift_type_id' => $block['shift_type_id'],
            'start_time' => $block['start_time'],
            'end_time' => $block['end_time'],
            'break_minutes' => $block['break_minutes'],
            'unit_id' => $block['unit_id'],
            'description' => $block['description'] ?? '',
        ];
        $this->correctionReason = '';
        $this->correctionError = '';
        $this->showCorrectionModal = true;
    }

    public function openCorrectionCreate(): void
    {
        $this->authorize('correct', PlannedShift::class);

        $this->correctionShiftId = null;
        $this->correctionIsNew = true;
        $this->correctionBlock = [
            'work_date' => (string) ($this->dayEditorContext['date'] ?? ''),
            'shift_type_id' => null,
            'start_time' => '',
            'end_time' => '',
            'break_minutes' => 0,
            'unit_id' => null,
            'description' => '',
        ];
        $this->correctionReason = '';
        $this->correctionError = '';
        $this->showCorrectionModal = true;
    }

    public function closeCorrection(): void
    {
        $this->reset([
            'showCorrectionModal',
            'correctionShiftId',
            'correctionIsNew',
            'correctionBlock',
            'correctionReason',
            'correctionError',
        ]);
    }

    public function saveCorrection(CorrectPlannedShiftAction $correct, LoadRosterDayEditorAction $load): void
    {
        $this->authorize('correct', PlannedShift::class);

        $block = [
            'work_date' => (string) ($this->correctionBlock['work_date'] ?? ''),
            'shift_type_id' => $this->correctionBlock['shift_type_id'] !== null && $this->correctionBlock['shift_type_id'] !== ''
                ? (int) $this->correctionBlock['shift_type_id']
                : null,
            'start_time' => $this->normalizeEditorTime($this->correctionBlock['start_time'] ?? null),
            'end_time' => $this->normalizeEditorTime($this->correctionBlock['end_time'] ?? null),
            'break_minutes' => (int) ($this->correctionBlock['break_minutes'] ?? 0),
            'unit_id' => $this->correctionBlock['unit_id'] !== null && $this->correctionBlock['unit_id'] !== ''
                ? (int) $this->correctionBlock['unit_id']
                : null,
            'description' => $this->correctionBlock['description'] ?? null,
        ];

        $op = $this->correctionIsNew
            ? [
                'type' => 'create',
                'worker_id' => (int) ($this->dayEditorContext['worker']['id'] ?? 0),
                'block' => $block,
            ]
            : [
                'type' => 'update',
                'planned_shift_id' => (int) $this->correctionShiftId,
                'block' => $block,
            ];

        $this->runCorrection($correct, $load, [$op]);
    }

    public function deleteCorrection(CorrectPlannedShiftAction $correct, LoadRosterDayEditorAction $load): void
    {
        $this->authorize('correct', PlannedShift::class);

        if ($this->correctionIsNew || $this->correctionShiftId === null) {
            return;
        }

        $this->runCorrection($correct, $load, [[
            'type' => 'delete',
            'planned_shift_id' => (int) $this->correctionShiftId,
        ]]);
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    private function runCorrection(
        CorrectPlannedShiftAction $correct,
        LoadRosterDayEditorAction $load,
        array $ops,
    ): void {
        try {
            $correct->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                $ops,
                $this->correctionReason,
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->correctionError = __($e->getMessage());

            return;
        }

        $workerId = (int) ($this->dayEditorContext['worker']['id'] ?? 0);
        $date = (string) ($this->dayEditorContext['date'] ?? '');
        $this->closeCorrection();
        if ($workerId !== 0 && $date !== '') {
            $this->openDayEditor($workerId, $date, $load);
        }
        session()->flash('time_flash', __('time.schedule.correction.done'));
        $this->dispatch('roster-week-changed');
    }

    public function setView(string $view): void
    {
        $this->view = $view === 'month' ? 'month' : 'week';
        Cookie::queue(self::VIEW_COOKIE, $this->view, self::VIEW_COOKIE_MINUTES);
        // Weergaveknoppen landen op de huidige periode: "Week" → deze week,
        // "Maand" → deze maand.
        $this->weekStart = $this->isMonth()
            ? now()->startOfMonth()->toDateString()
            : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function previousWeek(ResolveRosterPeriodAction $resolvePeriod): void
    {
        $cursor = Carbon::parse($this->weekStart);
        $this->weekStart = $this->isMonth()
            ? $cursor->subMonthNoOverflow()->startOfMonth()->toDateString()
            : $resolvePeriod->handle($this->weekStart, 'week')[0]->subWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function nextWeek(ResolveRosterPeriodAction $resolvePeriod): void
    {
        $cursor = Carbon::parse($this->weekStart);
        $this->weekStart = $this->isMonth()
            ? $cursor->addMonthNoOverflow()->startOfMonth()->toDateString()
            : $resolvePeriod->handle($this->weekStart, 'week')[0]->addWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function thisWeek(): void
    {
        $this->weekStart = $this->isMonth()
            ? now()->startOfMonth()->toDateString()
            : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function updatedTeamFilter(mixed $value): void
    {
        $this->teamFilter = $value === '' || $value === null ? null : (int) $value;
        $this->rememberScope();
        $this->dispatch('roster-week-changed');
    }

    public function updatedLocationFilter(mixed $value): void
    {
        $this->locationFilter = $value === '' || $value === null ? null : (int) $value;
        $this->rememberScope();
        $this->syncGroupFiltersFromLocation();
        $this->dispatch('roster-week-changed');
    }

    public function updatedShowWeekends(): void
    {
        $this->showWeekends = (bool) $this->showWeekends;
        $this->dispatch('roster-week-changed');
    }

    public function updatedShowStats(): void
    {
        $this->showStats = (bool) $this->showStats;
        Cookie::queue(self::STATS_COOKIE, $this->showStats ? '1' : '0', self::STATS_COOKIE_MINUTES);
        $this->dispatch('roster-week-changed');
    }

    public function updatedGroupUnitIds(): void
    {
        $this->groupUnitIds = array_values(array_map('intval', $this->groupUnitIds));
        $this->dispatch('roster-week-changed');
    }

    public function updatedShowUngrouped(): void
    {
        $this->showUngrouped = (bool) $this->showUngrouped;
        $this->dispatch('roster-week-changed');
    }

    private function syncGroupFiltersFromLocation(): void
    {
        if ($this->locationFilter === null) {
            $this->groupUnitIds = [];
            $this->showUngrouped = true;
            $this->groupsReady = false;

            return;
        }

        $this->groupUnitIds = Unit::query()
            ->where('location_id', $this->locationFilter)
            ->where('is_active', true)
            ->whereNotNull('roster_code')
            ->where('roster_code', '!=', '')
            ->orderBy('roster_code')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->showUngrouped = true;
        $this->groupsReady = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ListRosterWeekAction $list): array
    {
        $this->authorize('viewAny', PlannedShift::class);

        return $this->gridPayload($this->snapshot($list));
    }

    private function snapshot(ListRosterWeekAction $list): RosterWeekSnapshot
    {
        return $list->handle(
            (int) Tenancy::id(),
            $this->weekStart,
            $this->teamFilter,
            auth()->user(),
            $this->period(),
            $this->includeWeekends(),
            $this->locationFilter,
            $this->locationFilter !== null ? $this->groupUnitIds : null,
            $this->showUngrouped,
            true,
            $this->showStats,
        );
    }

    private function gridPayload(RosterWeekSnapshot $snapshot): array
    {
        $array = $snapshot->toArray();
        $array['invalid_message'] = __('time.schedule.errors.invalid_cells');
        $array['unsaved_message'] = __('time.schedule.unsaved.save_first');
        $array['name_column'] = __('time.schedule.column_name');
        if (auth()->user()?->can('viewAny', WorkShift::class)) {
            $array['hours_url'] = route('time.shifts.index');
            $array['worker_hours_hint'] = __('time.presence.open_worker_hours');
        }
        $array['attendance_open_hint'] = __('time.schedule.attendance.open_hours');
        $array['multi_replace_confirm'] = __('time.schedule.multi_replace_confirm');
        $array['multi_hint'] = __('time.schedule.multi_hint');
        $array['day_editor_hint'] = __('time.schedule.day_editor.hint');
        $array['locked_dates'] = array_values(array_filter(
            $array['dates'] ?? [],
            fn (string $date) => RosterHistoryLock::isLockedDate($date),
        ));
        $array['locked_hint'] = __('time.schedule.errors.locked_day');

        // Minimum bezetting: per datum vertaalde tooltip-lijnen; JS markeert
        // de dagheader rood en toont de lijnen als hover-ballon.
        $shortfalls = app(ListLocationStaffingShortfallsAction::class)->handle(
            (int) Tenancy::id(),
            $snapshot->dates,
            auth()->user()?->accessibleLocationIds(),
            $this->locationFilter,
        );
        $array['staffing_shortfalls_title'] = __('time.schedule.staffing.title');
        $array['staffing_shortfalls'] = collect($shortfalls['by_date'] ?? [])
            ->map(fn (array $items) => collect($items)
                ->groupBy('location')
                ->map(fn ($group, string $location) => [
                    'location' => $location,
                    'lines' => $group->map(fn (array $item) => __('time.schedule.staffing.missing', [
                        'code' => $item['code'],
                        'name' => $item['label'],
                        'actual' => $item['actual'],
                        'required' => $item['required'],
                    ]))->all(),
                ])
                ->values()
                ->all())
            ->all();

        return $array;
    }

    /**
     * @param  list<array{worker_id: int, date: string, raw: ?string}>  $cells
     */
    public function save(array $cells, SavePlannedShiftsAction $save): void
    {
        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);

        $normalized = [];
        foreach ($cells as $cell) {
            $normalized[] = [
                'worker_id' => (int) $cell['worker_id'],
                'date' => (string) $cell['date'],
                'raw' => (string) ($cell['raw'] ?? ''),
                'keep' => ! empty($cell['keep']),
            ];
        }

        Validator::make(
            [
                'week_start' => $this->weekStart,
                'period' => $this->period(),
                'include_weekends' => $this->includeWeekends(),
                'location_id' => $this->locationFilter,
                'worker_ids' => $workerIds,
                'cells' => $normalized,
            ],
            SavePlannedShiftsRequest::rulesFor(),
        )->validate();

        try {
            $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedShiftsData(
                    $this->weekStart,
                    $workerIds,
                    $normalized,
                    $this->period(),
                    $this->includeWeekends(),
                    $this->locationFilter,
                ),
                auth()->id(),
                auth()->user()?->accessibleLocationIds(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->dispatch('roster-save-failed', message: __($e->getMessage()), cells: $e instanceof RosterValidationException ? $e->cells : []);

            return;
        }

        session()->flash('time_flash', __('time.schedule.saved'));
        $this->dispatch('roster-week-changed');
    }

    public function openCopyModal(ResolveRosterPeriodAction $resolvePeriod): void
    {
        if ($this->isMonth()) {
            return;
        }

        $this->authorize('update', PlannedShift::class);
        [$monday] = $resolvePeriod->handle($this->weekStart, 'week');
        $this->copyTargetWeek = $monday->copy()->addWeek()->toDateString();
        $this->copyWeekCount = 1;
        $this->copyReport = [];
        $this->copyError = '';
        $this->showCopyModal = true;
    }

    public function closeCopyModal(): void
    {
        $this->showCopyModal = false;
        $this->copyReport = [];
        $this->copyError = '';
    }

    public function openRestoreModal(ListRosterRestorePointsAction $list): void
    {
        $this->authorize('update', PlannedShift::class);
        $this->restorePointId = null;
        $this->restoreDetail = [];
        $this->restoreError = '';
        $this->restoreDone = false;
        $this->restoreUndoPointId = null;
        $this->loadRestorePoints($list);
        $this->showRestoreModal = true;
    }

    public function closeRestoreModal(): void
    {
        $this->showRestoreModal = false;
        $this->restorePointId = null;
        $this->restoreDetail = [];
        $this->restoreError = '';
        $this->restoreDone = false;
        $this->restoreUndoPointId = null;
    }

    public function backToRestoreList(): void
    {
        $this->restorePointId = null;
        $this->restoreDetail = [];
        $this->restoreError = '';
        $this->restoreDone = false;
    }

    public function selectRestorePoint(int $pointId, RestoreRosterPointAction $restore): void
    {
        $this->authorize('update', PlannedShift::class);
        $this->restoreDone = false;
        $this->restoreError = '';
        $this->loadRestoreDetail($pointId, $restore);
    }

    public function confirmRestore(RestoreRosterPointAction $restore, ListRosterRestorePointsAction $list): void
    {
        $this->authorize('update', PlannedShift::class);
        if ($this->restorePointId === null || ($this->restoreDetail['blocked'] ?? false)) {
            return;
        }
        if (! ($this->restoreDetail['needs_confirmation'] ?? false)) {
            return;
        }

        try {
            Validator::make(
                [
                    'restore_point_id' => $this->restorePointId,
                    'confirmed' => true,
                    'seen_token' => (string) ($this->restoreDetail['token'] ?? ''),
                ],
                RestoreRosterPointRequest::rulesFor(),
            )->validate();
        } catch (ValidationException $e) {
            $this->restoreError = (string) collect($e->errors())->flatten()->first();

            return;
        }

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $point = RosterRestorePoint::query()->find($this->restorePointId);
        $actor = auth()->user();
        if (! $point instanceof RosterRestorePoint || ! $actor instanceof User) {
            $this->restoreError = __('time.schedule.restore.failed');

            return;
        }

        try {
            $undo = $restore->handle(
                $tenant,
                $actor,
                $point,
                true,
                (string) ($this->restoreDetail['token'] ?? ''),
            );
        } catch (RosterRestoreConflictException $e) {
            $this->restoreError = __($e->getMessage());
            $this->loadRestoreDetail((int) $point->id, $restore);

            return;
        } catch (RosterValidationException|InvalidArgumentException|AuthorizationException $e) {
            $this->restoreError = __($e->getMessage());

            return;
        } catch (QueryException) {
            $this->restoreError = __('time.schedule.restore.failed');

            return;
        }

        if ($undo === null) {
            $this->loadRestoreDetail((int) $point->id, $restore);

            return;
        }

        $this->showRestoredPeriod($point);
        $this->restoreDone = true;
        $this->restoreUndoPointId = (int) $undo->id;
        $this->restoreError = '';
        $this->loadRestorePoints($list);
        $this->dispatch('roster-week-changed');
    }

    public function undoRestore(RestoreRosterPointAction $restore): void
    {
        $this->authorize('update', PlannedShift::class);
        if ($this->restoreUndoPointId === null) {
            return;
        }

        $pointId = $this->restoreUndoPointId;
        $this->restoreDone = false;
        $this->restoreError = '';
        $this->loadRestoreDetail($pointId, $restore);
    }

    public function copyWeeks(CopyRosterWeeksAction $copy): void
    {
        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);

        try {
            Validator::make(
                [
                    'source_week_start' => $this->weekStart,
                    'target_week_start' => $this->copyTargetWeek,
                    'weeks' => $this->copyWeekCount,
                    'worker_ids' => $workerIds,
                ],
                CopyWeekRequest::rulesFor(),
            )->validate();
        } catch (ValidationException $e) {
            $this->copyError = (string) collect($e->errors())->flatten()->first();

            return;
        }

        try {
            $report = $copy->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new CopyRosterWeeksData(
                    $this->weekStart,
                    $this->copyTargetWeek,
                    $this->copyWeekCount,
                    $workerIds,
                ),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->copyError = __($e->getMessage());

            return;
        }

        $this->copyReport = array_map(fn (array $week) => [
            'week' => $week['week'],
            'status' => $week['status'],
            'created' => $week['created'],
            'skipped' => $week['skipped'],
            'message' => $week['message'] !== null ? __($week['message']) : null,
        ], $report['weeks']);
        $this->copyError = '';
        $this->dispatch('roster-week-changed');
    }

    public function publish(PublishWeekAction $publish): void
    {
        $this->authorize('publish', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);

        Validator::make(
            [
                'week_start' => $this->weekStart,
                'period' => $this->period(),
                'worker_ids' => $workerIds,
            ],
            PublishWeekRequest::rulesFor(),
        )->validate();

        try {
            $publish->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new PublishWeekData($this->weekStart, $workerIds, $this->period()),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            session()->flash('time_flash_error', __($e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.schedule.published'));
        $this->dispatch('roster-week-changed');
    }

    public function render(
        ListShiftTypesAction $listTypes,
        ListRosterWeekAction $listWeek,
    ) {
        $snapshot = $this->snapshot($listWeek);

        return view('livewire.time.roster-index', [
            'legendTypes' => $listTypes->handle((int) Tenancy::id(), true),
            'teams' => $snapshot->teams,
            'locations' => $snapshot->locations,
            'groupUnits' => $this->locationFilter !== null ? $snapshot->units : [],
            'weekLabel' => $this->periodLabel($snapshot),
            'snapshot' => $snapshot,
            'gridPayload' => $this->gridPayload($snapshot),
            'hasUnpublished' => collect($snapshot->cells)->contains(
                fn (array $cell) => collect($cell['shifts'] ?? [$cell])->contains(
                    fn (array $shift) => ($shift['status'] ?? null) === PlannedShiftStatus::Draft->value,
                ),
            ),
            'hasScope' => $this->teamFilter !== null || $this->locationFilter !== null,
            'isMonth' => $this->isMonth(),
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }

    private function period(): string
    {
        return $this->isMonth() ? 'month' : 'week';
    }

    private function isMonth(): bool
    {
        return $this->view === 'month';
    }

    private function includeWeekends(): bool
    {
        return $this->showWeekends;
    }

    private function periodLabel(RosterWeekSnapshot $snapshot): string
    {
        if ($this->isMonth()) {
            return $snapshot->monthLabel;
        }

        $start = Carbon::parse($snapshot->weekStart)->startOfWeek(Carbon::MONDAY);
        $number = $start->isoWeek();
        $isCurrent = $start->isSameWeek(now(), Carbon::MONDAY);

        return $isCurrent
            ? __('time.schedule.week_current', ['number' => $number])
            : __('time.schedule.week_numbered', ['number' => $number]);
    }

    private function normalizeView(): void
    {
        $this->view = $this->view === 'month' ? 'month' : 'week';
    }

    private function restoreRememberedView(): void
    {
        if (request()->query->has('view')) {
            return;
        }

        $remembered = request()->cookie(self::VIEW_COOKIE);
        if (in_array($remembered, ['week', 'month'], true)) {
            $this->view = $remembered;
        }
    }

    private function restoreRememberedStats(): void
    {
        if (request()->query->has('stats')) {
            return;
        }

        $remembered = request()->cookie(self::STATS_COOKIE);
        if (in_array($remembered, ['0', '1'], true)) {
            $this->showStats = $remembered === '1';
        }
    }

    private function restoreRememberedScope(): void
    {
        if (request()->query->has('team') || request()->query->has('location')) {
            return;
        }

        $remembered = json_decode((string) request()->cookie(self::SCOPE_COOKIE, ''), true);
        if (! is_array($remembered)) {
            return;
        }

        $this->teamFilter = isset($remembered['team']) ? (int) $remembered['team'] : null;
        $this->locationFilter = isset($remembered['location']) ? (int) $remembered['location'] : null;
    }

    private function showRestoredPeriod(RosterRestorePoint $point): void
    {
        $dates = [];
        foreach (is_array($point->scope) ? $point->scope : [] as $item) {
            if (! empty($item['date'])) {
                $dates[] = (string) $item['date'];
            }
        }
        if ($dates === []) {
            return;
        }

        $anchor = min($dates);
        $target = $this->isMonth()
            ? Carbon::parse($anchor)->startOfMonth()->toDateString()
            : Carbon::parse($anchor)->startOfWeek(Carbon::MONDAY)->toDateString();
        if ($this->weekStart !== $target) {
            $this->weekStart = $target;
        }
    }

    private function loadRestorePoints(ListRosterRestorePointsAction $list): void
    {
        $listed = $list->handle(
            Tenant::query()->findOrFail(Tenancy::id()),
            auth()->user(),
        );
        $this->restorePointsCapped = $listed['capped'];
        $this->restorePoints = array_map(fn (array $point): array => [
            'id' => $point['id'],
            'when' => $point['created_at'] === null
                ? ''
                : __('time.schedule.restore.when', [
                    'date' => Carbon::parse($point['created_at'])->timezone(config('app.timezone'))->format('d/m/Y'),
                    'time' => Carbon::parse($point['created_at'])->timezone(config('app.timezone'))->format('H:i'),
                ]),
            'source' => __('time.schedule.restore.sources.'.$point['source']),
            'workers' => $point['workers'] === []
                ? __('time.schedule.restore.unknown_worker')
                : implode(', ', array_column($point['workers'], 'name')),
            'worker_map' => array_column($point['workers'], 'name', 'id'),
            'dates' => $this->formatRestoreDates($point['dates']),
        ], $listed['points']);
    }

    private function loadRestoreDetail(int $pointId, RestoreRosterPointAction $restore): void
    {
        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $point = RosterRestorePoint::query()->find($pointId);
        $actor = auth()->user();
        if (! $point instanceof RosterRestorePoint || ! $actor instanceof User) {
            $this->restoreError = __('time.schedule.restore.failed');
            $this->restoreDetail = [];

            return;
        }

        try {
            $preview = $restore->preview($tenant, $actor, $point);
        } catch (AuthorizationException $e) {
            $this->restoreError = __($e->getMessage());
            $this->restoreDetail = [];

            return;
        }

        $this->restorePointId = (int) $point->id;
        $workerMap = [];
        foreach ($this->restorePoints as $row) {
            if ((int) $row['id'] === (int) $point->id) {
                $workerMap = is_array($row['worker_map'] ?? null) ? $row['worker_map'] : [];
            }
        }
        $this->restoreDetail = $this->describeRestore(
            $point,
            $preview['assessment'],
            $preview['current'],
            $preview['saved'],
            $workerMap,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $saved
     * @param  array<int|string, string>  $workerMap
     * @return array<string, mixed>
     */
    private function describeRestore(
        RosterRestorePoint $point,
        RosterRestoreAssessment $assessment,
        array $current,
        array $saved,
        array $workerMap,
    ): array {
        $workerNames = [];
        foreach ($workerMap as $id => $name) {
            $workerNames[(int) $id] = (string) $name;
        }
        $currentById = [];
        foreach ($current as $row) {
            $currentById[(int) $row['planned_shift_id']] = $row;
        }
        $savedById = [];
        foreach ($saved as $row) {
            $savedById[(int) $row['planned_shift_id']] = $row;
        }

        $linesByEffect = ['added' => [], 'removed' => [], 'changed' => []];
        foreach ($assessment->differences as $difference) {
            $effect = $difference['type'] === 'added' || $difference['type'] === 'removed'
                ? $difference['type']
                : 'changed';
            $savedRow = $savedById[(int) $difference['planned_shift_id']] ?? null;
            $currentRow = $currentById[(int) $difference['planned_shift_id']] ?? null;
            $name = $workerNames[(int) $difference['worker_id']] ?? __('time.schedule.restore.unknown_worker');
            $savedLabel = $this->restoreShiftLabel(is_array($savedRow) ? $savedRow : []);
            $currentLabel = $this->restoreShiftLabel(is_array($currentRow) ? $currentRow : []);
            $line = $name.' — '.match ($effect) {
                'added' => $currentLabel,
                'removed' => $savedLabel,
                default => $savedLabel === $currentLabel
                    ? $currentLabel.$this->restoreFieldSuffix($difference['fields'])
                    : $savedLabel.' → '.$currentLabel,
            };
            $linesByEffect[$effect][(string) $difference['date']][] = $line;
        }

        $groups = [];
        foreach (['added' => 'remove', 'removed' => 'return', 'changed' => 'change'] as $effect => $key) {
            if ($linesByEffect[$effect] === []) {
                continue;
            }
            $count = array_sum(array_map('count', $linesByEffect[$effect]));
            $days = [];
            $dates = array_keys($linesByEffect[$effect]);
            sort($dates);
            foreach ($dates as $date) {
                $days[] = [
                    'label' => Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('D d/m'),
                    'lines' => $linesByEffect[$effect][$date],
                ];
            }
            $groups[] = [
                'heading' => trans_choice('time.schedule.restore.effect.'.$key, $count, ['count' => $count]),
                'hint' => __('time.schedule.restore.effect_hint.'.$key),
                'days' => $days,
            ];
        }

        $blocks = [];
        foreach ($assessment->blocks as $block) {
            $blocks[] = __($this->restoreNoticeKey('block', (string) $block['code']), [
                'date' => isset($block['date']) ? Carbon::parse((string) $block['date'])->format('d/m/Y') : '',
                'name' => $workerNames[(int) ($block['worker_id'] ?? 0)] ?? __('time.schedule.restore.unknown_worker'),
            ]);
        }

        $warnings = [];
        foreach ($assessment->warnings as $warning) {
            $warnings[] = __($this->restoreNoticeKey('warning', (string) $warning['code']), [
                'date' => isset($warning['date']) ? Carbon::parse((string) $warning['date'])->format('d/m/Y') : '',
                'name' => $workerNames[(int) ($warning['worker_id'] ?? 0)] ?? __('time.schedule.restore.unknown_worker'),
            ]);
        }

        return [
            'blocked' => $assessment->blocked(),
            'needs_confirmation' => $assessment->needsConfirmation(),
            'when' => $point->created_at === null
                ? ''
                : __('time.schedule.restore.when', [
                    'date' => $point->created_at->timezone(config('app.timezone'))->format('d/m/Y'),
                    'time' => $point->created_at->timezone(config('app.timezone'))->format('H:i'),
                ]),
            'source' => __('time.schedule.restore.sources.'.$point->source->value),
            'token' => $assessment->token(),
            'groups' => $groups,
            'blocks' => $blocks,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function restoreShiftLabel(array $row): string
    {
        $code = isset($row['shift_type_code']) && $row['shift_type_code'] !== ''
            ? (string) $row['shift_type_code']
            : '';
        $start = isset($row['start_time']) && $row['start_time'] !== '' ? (string) $row['start_time'] : '';
        $end = isset($row['end_time']) && $row['end_time'] !== '' ? (string) $row['end_time'] : '';
        $time = $start !== '' && $end !== '' ? $start.'–'.$end : '';
        if ($code !== '' && $time !== '') {
            return $code.' '.$time;
        }

        return $code !== '' ? $code : ($time !== '' ? $time : '—');
    }

    /**
     * @param  list<string>  $fields
     */
    private function restoreFieldLabels(array $fields): string
    {
        $labels = [];
        foreach ($fields as $field) {
            $key = 'time.schedule.restore.fields.'.$field;
            $label = __($key);
            $labels[] = $label === $key ? $field : $label;
        }

        return implode(', ', $labels);
    }

    /**
     * @param  list<string>  $fields
     */
    private function restoreFieldSuffix(array $fields): string
    {
        $label = $this->restoreFieldLabels($fields);

        return $label === '' ? '' : ' ('.$label.')';
    }

    private function restoreNoticeKey(string $group, string $code): string
    {
        $key = 'time.schedule.restore.'.$group.'.'.$code;

        return __($key) === $key ? 'time.schedule.errors.restore_blocked' : $key;
    }

    /**
     * @param  list<string>  $dates
     */
    private function formatRestoreDates(array $dates): string
    {
        $formatted = array_map(
            fn (string $date): string => Carbon::parse($date)->format('d/m/Y'),
            $dates,
        );
        if (count($formatted) <= 6) {
            return implode(', ', $formatted);
        }

        return implode(', ', array_slice($formatted, 0, 5)).' …';
    }

    private function rememberScope(): void
    {
        Cookie::queue(self::SCOPE_COOKIE, json_encode([
            'team' => $this->teamFilter,
            'location' => $this->locationFilter,
        ]), self::SCOPE_COOKIE_MINUTES);
    }
}
