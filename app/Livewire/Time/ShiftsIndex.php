<?php

namespace App\Livewire\Time;

use App\Actions\Time\CorrectWorkShiftAction;
use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Http\Requests\Time\CorrectWorkShiftRequest;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
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

    public bool $showCorrectionModal = false;
    public ?int $correctionShiftId = null;
    public string $correctionClockIn = '';
    public string $correctionClockOut = '';
    public int $correctionBreakMinutes = 0;
    public string $correctionReason = '';

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

    public function openCorrection(int $shiftId): void
    {
        $shift = WorkShift::query()->findOrFail($shiftId);
        $this->authorize('correct', $shift);

        $this->correctionShiftId = $shift->id;
        $this->correctionClockIn = $shift->clock_in_at->format('Y-m-d\TH:i');
        $this->correctionClockOut = $shift->clock_out_at?->format('Y-m-d\TH:i') ?? '';
        $this->correctionBreakMinutes = (int) $shift->total_break_minutes;
        $this->correctionReason = '';
        $this->showCorrectionModal = true;
    }

    public function closeCorrection(): void
    {
        $this->showCorrectionModal = false;
        $this->correctionShiftId = null;
        $this->correctionReason = '';
    }

    public function saveCorrection(CorrectWorkShiftAction $correct): void
    {
        $shift = WorkShift::query()->findOrFail($this->correctionShiftId);
        $this->authorize('correct', $shift);

        $validated = $this->validate(
            [
                'correctionClockIn' => ['required', 'date'],
                'correctionClockOut' => ['required', 'date', 'after:correctionClockIn'],
                'correctionBreakMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'correctionReason' => ['required', 'string', 'min:3', 'max:500'],
            ],
            [],
            [
                'correctionClockIn' => __('time.corrections.fields.clock_in'),
                'correctionClockOut' => __('time.corrections.fields.clock_out'),
                'correctionBreakMinutes' => __('time.corrections.fields.break_minutes'),
                'correctionReason' => __('time.corrections.fields.reason'),
            ],
        );

        try {
            $correct->handle($shift, [
                'clock_in_at' => $validated['correctionClockIn'],
                'clock_out_at' => $validated['correctionClockOut'],
                'total_break_minutes' => (int) $validated['correctionBreakMinutes'],
                'reason' => $validated['correctionReason'],
            ], (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            $messageKey = match ($e->getMessage()) {
                'break_exceeds_duration' => 'correctionBreakMinutes',
                default => 'correctionClockOut',
            };
            $this->addError($messageKey, __('time.corrections.errors.'.$e->getMessage()));

            return;
        }

        $this->closeCorrection();
        session()->flash('time_flash', __('time.corrections.saved'));
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
            'printUrl' => route('time.shifts.print', array_filter([
                'from' => $this->from,
                'to' => $this->to,
                'team' => $this->teamFilter,
                'worker' => $this->workerFilter,
                'clock_point' => $this->clockPointFilter,
            ])),
        ]);
    }
}
