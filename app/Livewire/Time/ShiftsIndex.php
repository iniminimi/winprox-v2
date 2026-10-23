<?php

namespace App\Livewire\Time;

use App\Actions\Time\ApplyRequiredBreakToWorkShiftAction;
use App\Actions\Time\CorrectWorkShiftAction;
use App\Livewire\Concerns\ManagesManualClockIn;
use App\Livewire\Concerns\ManagesWorkShiftForceClose;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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
    use ManagesManualClockIn;
    use ManagesWorkShiftForceClose;
    use ProvidesTimeNavAlarmCount;
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
    public string $correctionClockInDate = '';
    public string $correctionClockInTime = '';
    public string $correctionClockOutDate = '';
    public string $correctionClockOutTime = '';
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

    public function openCorrection(int $shiftId): void
    {
        $shift = WorkShift::query()->findOrFail($shiftId);
        $this->authorize('correct', $shift);

        $this->correctionShiftId = $shift->id;
        $this->correctionClockInDate = $shift->clock_in_at->format('Y-m-d');
        $this->correctionClockInTime = $shift->clock_in_at->format('H:i');
        $this->correctionClockOutDate = $shift->clock_out_at?->format('Y-m-d') ?? '';
        $this->correctionClockOutTime = $shift->clock_out_at?->format('H:i') ?? '';
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
                'correctionClockInDate' => ['required', 'date_format:Y-m-d'],
                'correctionClockInTime' => ['required', 'date_format:H:i'],
                'correctionClockOutDate' => ['nullable', 'date_format:Y-m-d', 'required_with:correctionClockOutTime'],
                'correctionClockOutTime' => ['nullable', 'date_format:H:i', 'required_with:correctionClockOutDate'],
                'correctionBreakMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'correctionReason' => ['required', 'string', 'min:3', 'max:500'],
            ],
            [],
            [
                'correctionClockInDate' => __('time.corrections.fields.clock_in'),
                'correctionClockInTime' => __('time.corrections.fields.clock_in'),
                'correctionClockOutDate' => __('time.corrections.fields.clock_out'),
                'correctionClockOutTime' => __('time.corrections.fields.clock_out'),
                'correctionBreakMinutes' => __('time.corrections.fields.break_minutes'),
                'correctionReason' => __('time.corrections.fields.reason'),
            ],
        );

        $clockIn = $validated['correctionClockInDate'].' '.$validated['correctionClockInTime'];
        $clockOut = ($validated['correctionClockOutDate'] ?? '') !== ''
            ? $validated['correctionClockOutDate'].' '.$validated['correctionClockOutTime']
            : null;

        if ($clockOut !== null && ! \Illuminate\Support\Carbon::parse($clockOut)->gt(\Illuminate\Support\Carbon::parse($clockIn))) {
            $this->addError('correctionClockOutTime', __('time.corrections.errors.clock_out_before_clock_in'));

            return;
        }

        try {
            $correct->handle($shift, [
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'total_break_minutes' => (int) $validated['correctionBreakMinutes'],
                'reason' => $validated['correctionReason'],
            ], (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            $messageKey = match ($e->getMessage()) {
                'break_exceeds_duration' => 'correctionBreakMinutes',
                default => 'correctionClockOutTime',
            };
            $this->addError($messageKey, __('time.corrections.errors.'.$e->getMessage()));

            return;
        }

        $this->closeCorrection();
        session()->flash('time_flash', __('time.corrections.saved'));
    }

    public function applyRequiredBreak(int $shiftId, ApplyRequiredBreakToWorkShiftAction $apply): void
    {
        $shift = WorkShift::query()->findOrFail($shiftId);
        $this->authorize('applyRequiredBreak', $shift);

        try {
            $apply->handle($shift, (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash_error', __('time.required_break.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.required_break.saved'));
    }

    public function render()
    {
        $query = WorkShift::query()
            ->with([
                'worker',
                'team.translations',
                'clockInClockPoint.location.translations',
                'presenceClockPoint.location.translations',
                'clockOutClockPoint.location.translations',
                'taskLogs.task',
                'breaks',
                'visits' => fn ($q) => $q->orderBy('started_at')->with(['unit', 'location']),
            ])
            ->when($this->from !== '', fn ($q) => $q->where('clock_in_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('clock_in_at', '<=', $this->to.' 23:59:59'))
            ->when($this->teamFilter, fn ($q) => $q->where('internal_team_id', $this->teamFilter))
            ->when($this->workerFilter, fn ($q) => $q->where('worker_id', $this->workerFilter))
            ->when($this->clockPointFilter, fn ($q) => $q->where('clock_in_clock_point_id', $this->clockPointFilter))
            ->orderByDesc('clock_in_at');

        $shifts = $query->paginate(25);

        $manualClockInOptions = $this->showManualClockInModal
            ? $this->manualClockInFormOptions(auth()->user()?->accessibleLocationIds())
            : ['workers' => collect(), 'clockPoints' => collect()];

        return view('livewire.time.shifts-index', [
            'shifts' => $shifts,
            'plannedMap' => $this->plannedShiftsMap($shifts->getCollection()),
            'teams' => InternalTeam::query()->with('translations')->orderBy('sort_order')->orderBy('name')->get(),
            'workers' => Worker::query()->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'clockPoints' => ClockPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
            'manualClockInWorkers' => $manualClockInOptions['workers'],
            'manualClockInClockPoints' => $manualClockInOptions['clockPoints'],
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
            'alarmCount' => $this->timeNavAlarmCount(),
            // Manueel inklokken hoort bij facility-beheer; Checkmate klokt
            // alleen via de eigen gsm — knop weglaten.
            'checkmateMode' => CheckmateMode::isActive(Tenant::query()->find((int) Tenancy::id())),
        ]);
    }

    /**
     * @param  Collection<int, WorkShift>  $shifts
     * @return array<string, list<PlannedShift>>
     */
    private function plannedShiftsMap(Collection $shifts): array
    {
        $workerIds = $shifts->pluck('worker_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        $dates = $shifts->pluck('clock_in_at')->filter()->map(fn ($at) => $at->toDateString())->unique()->sort()->values();
        if ($workerIds === [] || $dates->isEmpty()) {
            return [];
        }

        $map = [];
        PlannedShift::query()
            ->whereIn('worker_id', $workerIds)
            ->whereBetween('work_date', [$dates->first().' 00:00:00', $dates->last().' 23:59:59'])
            ->with('shiftType')
            ->orderBy('start_time')
            ->get()
            ->each(function (PlannedShift $planned) use (&$map): void {
                $map[(int) $planned->worker_id.':'.$planned->work_date->toDateString()][] = $planned;
            });

        return $map;
    }
}
