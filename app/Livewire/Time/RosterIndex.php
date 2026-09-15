<?php

namespace App\Livewire\Time;

use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ResolveRosterPeriodAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\RosterWeekSnapshot;
use App\Data\Time\SavePlannedShiftsData;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\CopyWeekRequest;
use App\Http\Requests\Time\PublishWeekRequest;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
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

    #[Url(as: 'week')]
    public string $weekStart = '';

    #[Url(as: 'view')]
    public string $view = 'week';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    public function mount(ResolveRosterPeriodAction $resolvePeriod): void
    {
        $this->authorize('viewAny', PlannedShift::class);
        $this->normalizeView();

        if ($this->weekStart === '') {
            $this->weekStart = $this->isMonth()
                ? now()->startOfMonth()->toDateString()
                : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            [$start] = $resolvePeriod->handle($this->weekStart, $this->period());
            $this->weekStart = $start->toDateString();
        }
    }

    public function setView(string $view, ResolveRosterPeriodAction $resolvePeriod): void
    {
        $this->view = $view === 'month' ? 'month' : 'week';
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
        $this->dispatch('roster-week-changed');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ListRosterWeekAction $list): array
    {
        $this->authorize('viewAny', PlannedShift::class);
        $snapshot = $list->handle(
            (int) Tenancy::id(),
            $this->weekStart,
            $this->teamFilter,
            auth()->user(),
            $this->period(),
        );

        $array = $snapshot->toArray();
        $array['invalid_message'] = __('time.schedule.errors.invalid_cells');

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
            ];
        }

        Validator::make(
            [
                'week_start' => $this->weekStart,
                'period' => $this->period(),
                'worker_ids' => $workerIds,
                'cells' => $normalized,
            ],
            SavePlannedShiftsRequest::rulesFor(),
        )->validate();

        try {
            $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedShiftsData($this->weekStart, $workerIds, $normalized, $this->period()),
                auth()->id(),
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
        $snapshot = $listWeek->handle(
            (int) Tenancy::id(),
            $this->weekStart,
            $this->teamFilter,
            auth()->user(),
            $this->period(),
        );

        return view('livewire.time.roster-index', [
            'legendTypes' => $listTypes->handle((int) Tenancy::id(), true),
            'teams' => $snapshot->teams,
            'weekLabel' => $this->periodLabel($snapshot),
            'snapshot' => $snapshot,
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

    private function periodLabel(RosterWeekSnapshot $snapshot): string
    {
        if ($this->isMonth()) {
            return $snapshot->monthLabel;
        }

        $locale = app()->getLocale();
        $start = Carbon::parse($snapshot->weekStart)->locale($locale);
        $end = Carbon::parse($snapshot->weekEnd)->locale($locale);

        if ($start->year === $end->year) {
            return $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y');
        }

        return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
    }

    private function normalizeView(): void
    {
        $this->view = $this->view === 'month' ? 'month' : 'week';
    }
}
