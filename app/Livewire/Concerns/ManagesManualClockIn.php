<?php

namespace App\Livewire\Concerns;

use App\Actions\Time\ManualClockInWorkShiftAction;
use App\Http\Requests\Time\ManualClockInWorkShiftRequest;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use InvalidArgumentException;

trait ManagesManualClockIn
{
    public bool $showManualClockInModal = false;

    public ?int $manualClockInWorkerId = null;

    public ?int $manualClockInClockPointId = null;

    public string $manualClockInReason = '';

    public function openManualClockIn(): void
    {
        $this->authorize('manualClockIn', WorkShift::class);

        $this->manualClockInWorkerId = null;
        $this->manualClockInClockPointId = null;
        $this->manualClockInReason = '';
        $this->showManualClockInModal = true;
        $this->resetErrorBag(['manualClockInWorkerId', 'manualClockInClockPointId', 'manualClockInReason']);
    }

    public function closeManualClockIn(): void
    {
        $this->showManualClockInModal = false;
        $this->manualClockInWorkerId = null;
        $this->manualClockInClockPointId = null;
        $this->manualClockInReason = '';
        $this->resetErrorBag(['manualClockInWorkerId', 'manualClockInClockPointId', 'manualClockInReason']);
    }

    public function confirmManualClockIn(ManualClockInWorkShiftAction $manualClockIn): void
    {
        $this->authorize('manualClockIn', WorkShift::class);

        $rules = ManualClockInWorkShiftRequest::rulesFor();
        $validated = $this->validate(
            [
                'manualClockInWorkerId' => $rules['worker_id'],
                'manualClockInClockPointId' => $rules['clock_point_id'],
                'manualClockInReason' => $rules['reason'],
            ],
            [],
            [
                'manualClockInWorkerId' => __('time.manual_clock_in.fields.worker'),
                'manualClockInClockPointId' => __('time.manual_clock_in.fields.clock_point'),
                'manualClockInReason' => __('time.manual_clock_in.fields.reason'),
            ],
        );

        $worker = Worker::query()->findOrFail($validated['manualClockInWorkerId']);
        $clockPoint = ClockPoint::query()->findOrFail($validated['manualClockInClockPointId']);

        try {
            $manualClockIn->handle(
                $worker,
                $clockPoint,
                $validated['manualClockInReason'],
                (int) Tenancy::id(),
                auth()->id(),
                auth()->user()?->accessibleLocationIds(),
            );
        } catch (InvalidArgumentException $e) {
            $message = match ($e->getMessage()) {
                'shift_already_open' => __('time.manual_clock_in.errors.shift_already_open'),
                'worker_inactive' => __('time.manual_clock_in.errors.worker_inactive'),
                'clock_point_inactive' => __('time.manual_clock_in.errors.clock_point_inactive'),
                'worker_location_not_allowed' => __('time.manual_clock_in.errors.worker_location_not_allowed'),
                'clock_point_not_allowed' => __('time.manual_clock_in.errors.clock_point_not_allowed'),
                default => null,
            };

            if ($message === null) {
                throw $e;
            }

            $this->addError('manualClockInReason', $message);

            return;
        }

        $this->closeManualClockIn();
        session()->flash('time_flash', __('time.manual_clock_in.saved'));
    }

    /**
     * @param  list<int>|null  $actorLocationIds
     * @return array{workers: \Illuminate\Support\Collection<int, Worker>, clockPoints: \Illuminate\Support\Collection<int, ClockPoint>}
     */
    protected function manualClockInFormOptions(?array $actorLocationIds): array
    {
        $openWorkerIds = WorkShift::query()->open()->pluck('worker_id');

        $clockPointsQuery = ClockPoint::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($actorLocationIds !== null) {
            $clockPointsQuery->whereIn('location_id', $actorLocationIds);
        }

        return [
            'workers' => Worker::query()
                ->where('is_active', true)
                ->whereNotIn('id', $openWorkerIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'clockPoints' => $clockPointsQuery->get(),
        ];
    }
}
