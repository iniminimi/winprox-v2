<?php

namespace App\Livewire\Time;

use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ResolveRosterWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
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

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    public function mount(ResolveRosterWeekAction $resolveWeek): void
    {
        $this->authorize('viewAny', PlannedShift::class);

        if ($this->weekStart === '') {
            $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            [$monday] = $resolveWeek->handle($this->weekStart);
            $this->weekStart = $monday->toDateString();
        }
    }

    public function previousWeek(ResolveRosterWeekAction $resolveWeek): void
    {
        [$monday] = $resolveWeek->handle($this->weekStart);
        $this->weekStart = $monday->subWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function nextWeek(ResolveRosterWeekAction $resolveWeek): void
    {
        [$monday] = $resolveWeek->handle($this->weekStart);
        $this->weekStart = $monday->addWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function thisWeek(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
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
                'worker_ids' => $workerIds,
                'cells' => $normalized,
            ],
            SavePlannedShiftsRequest::rulesFor(),
        )->validate();

        try {
            $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedShiftsData($this->weekStart, $workerIds, $normalized),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->dispatch('roster-save-failed', message: __($e->getMessage()), cells: $e instanceof RosterValidationException ? $e->cells : []);

            return;
        }

        session()->flash('time_flash', __('time.schedule.saved'));
        $this->dispatch('roster-week-changed');
    }

    public function copyToNextWeek(CopyWeekAction $copy, ResolveRosterWeekAction $resolveWeek): void
    {
        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);
        [$monday] = $resolveWeek->handle($this->weekStart);
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
                'worker_ids' => $workerIds,
            ],
            PublishWeekRequest::rulesFor(),
        )->validate();

        try {
            $publish->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new PublishWeekData($this->weekStart, $workerIds),
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
        );

        return view('livewire.time.roster-index', [
            'legendTypes' => $listTypes->handle((int) Tenancy::id(), true),
            'teams' => $snapshot->teams,
            'weekLabel' => $snapshot->weekStart.' – '.$snapshot->weekEnd,
            'snapshot' => $snapshot,
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }
}
