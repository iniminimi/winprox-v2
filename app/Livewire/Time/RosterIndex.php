<?php

namespace App\Livewire\Time;

use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\LoadRosterDayEditorAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ResolveRosterPeriodAction;
use App\Actions\Time\SavePlannedDayAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\RosterWeekSnapshot;
use App\Data\Time\SavePlannedDayData;
use App\Data\Time\SavePlannedShiftsData;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\CopyWeekRequest;
use App\Http\Requests\Time\PublishWeekRequest;
use App\Http\Requests\Time\SavePlannedDayRequest;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
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

    public function mount(ResolveRosterPeriodAction $resolvePeriod): void
    {
        $this->authorize('viewAny', PlannedShift::class);
        $this->normalizeView();
        $this->restoreRememberedView();
        $this->restoreRememberedScope();

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
        ], $this->dayEditorContext['blocks']);
        if ($this->dayEditorBlocks === []) {
            $this->dayEditorBlocks[] = $this->emptyDayEditorBlock();
        }
        $this->dayEditorError = '';
        $this->showDayEditor = true;
    }

    public function closeDayEditor(): void
    {
        $this->showDayEditor = false;
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

    public function setView(string $view, ResolveRosterPeriodAction $resolvePeriod): void
    {
        $this->view = $view === 'month' ? 'month' : 'week';
        Cookie::queue(self::VIEW_COOKIE, $this->view, self::VIEW_COOKIE_MINUTES);
        [$start] = $resolvePeriod->handle($this->weekStart !== '' ? $this->weekStart : now()->toDateString(), $this->period());
        $this->weekStart = $start->toDateString();
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

        $this->groupUnitIds = \App\Models\Unit::query()
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
        }
        $array['attendance_open_hint'] = __('time.schedule.attendance.open_hours');
        $array['multi_replace_confirm'] = __('time.schedule.multi_replace_confirm');
        $array['multi_hint'] = __('time.schedule.multi_hint');
        $array['day_editor_hint'] = __('time.schedule.day_editor.hint');

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

    public function copyToNextWeek(CopyWeekAction $copy, ResolveRosterPeriodAction $resolvePeriod): void
    {
        if ($this->isMonth()) {
            return;
        }

        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);
        [$monday] = $resolvePeriod->handle($this->weekStart, 'week');
        $target = $monday->copy()->addWeek()->toDateString();

        Validator::make(
            [
                'source_week_start' => $this->weekStart,
                'target_week_start' => $target,
                'worker_ids' => $workerIds,
            ],
            CopyWeekRequest::rulesFor(),
        )->validate();

        try {
            $copy->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new CopyWeekData($this->weekStart, $target, $workerIds),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            session()->flash('time_flash_error', __($e->getMessage()));

            return;
        }

        $this->weekStart = $target;
        session()->flash('time_flash', __('time.schedule.copied'));
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

    public function render(ListShiftTypesAction $listTypes, ListRosterWeekAction $listWeek)
    {
        $snapshot = $this->snapshot($listWeek);

        return view('livewire.time.roster-index', [
            'legendTypes' => $listTypes->handle((int) Tenancy::id(), true),
            'teams' => $snapshot->teams,
            'locations' => $snapshot->locations,
            'groupUnits' => $this->locationFilter !== null ? $snapshot->units : [],
            'weekLabel' => $this->periodLabel($snapshot),
            'snapshot' => $snapshot,
            'gridPayload' => $this->gridPayload($snapshot),
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

    private function rememberScope(): void
    {
        Cookie::queue(self::SCOPE_COOKIE, json_encode([
            'team' => $this->teamFilter,
            'location' => $this->locationFilter,
        ]), self::SCOPE_COOKIE_MINUTES);
    }
}
