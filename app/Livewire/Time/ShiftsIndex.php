<?php

namespace App\Livewire\Time;

use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ShiftsIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'worker')]
    public ?int $workerFilter = null;

    #[Url(as: 'clock_point')]
    public ?int $clockPointFilter = null;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);

        if ($this->from === '') {
            $this->from = now()->startOfMonth()->format('Y-m-d');
        }
        if ($this->to === '') {
            $this->to = now()->format('Y-m-d');
        }
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function forceClose(int $shiftId, ForceCloseWorkShiftAction $forceClose): void
    {
        $shift = WorkShift::query()->findOrFail($shiftId);
        $this->authorize('forceClose', $shift);

        $forceClose->handle($shift, (int) Tenancy::id(), auth()->id());
        session()->flash('time_flash', __('time.presence.force_closed'));
    }

    public function render()
    {
        $query = WorkShift::query()
            ->with(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint'])
            ->when($this->from !== '', fn ($q) => $q->where('clock_in_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('clock_in_at', '<=', $this->to.' 23:59:59'))
            ->when($this->teamFilter, fn ($q) => $q->where('internal_team_id', $this->teamFilter))
            ->when($this->workerFilter, fn ($q) => $q->where('worker_id', $this->workerFilter))
            ->when($this->clockPointFilter, fn ($q) => $q->where('clock_in_clock_point_id', $this->clockPointFilter))
            ->orderByDesc('clock_in_at');

        return view('livewire.time.shifts-index', [
            'shifts' => $query->paginate(25),
            'teams' => InternalTeam::query()->orderBy('sort_order')->orderBy('name')->get(),
            'workers' => Worker::query()->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'clockPoints' => ClockPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
            'exportUrl' => route('time.shifts.export', array_filter([
                'from' => $this->from,
                'to' => $this->to,
                'team' => $this->teamFilter,
                'worker' => $this->workerFilter,
                'clock_point' => $this->clockPointFilter,
            ])),
        ]);
    }
}
