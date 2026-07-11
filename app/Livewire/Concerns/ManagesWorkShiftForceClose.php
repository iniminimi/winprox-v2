<?php

namespace App\Livewire\Concerns;

use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Http\Requests\Time\ForceCloseWorkShiftRequest;
use App\Models\WorkShift;
use App\Support\Tenancy;
use InvalidArgumentException;

trait ManagesWorkShiftForceClose
{
    public bool $showForceCloseModal = false;

    public ?int $forceCloseShiftId = null;

    public string $forceCloseReason = '';

    public string $forceCloseWorkerLabel = '';

    public function openForceClose(int $shiftId): void
    {
        $shift = WorkShift::query()->with('worker')->findOrFail($shiftId);
        $this->authorize('forceClose', $shift);

        $this->forceCloseShiftId = $shift->id;
        $this->forceCloseReason = '';
        $this->forceCloseWorkerLabel = $shift->worker?->displayName() ?? '—';
        $this->showForceCloseModal = true;
    }

    public function closeForceClose(): void
    {
        $this->showForceCloseModal = false;
        $this->forceCloseShiftId = null;
        $this->forceCloseReason = '';
        $this->forceCloseWorkerLabel = '';
    }

    public function confirmForceClose(ForceCloseWorkShiftAction $forceClose): void
    {
        $shift = WorkShift::query()->findOrFail($this->forceCloseShiftId);
        $this->authorize('forceClose', $shift);

        $validated = $this->validate(
            ['forceCloseReason' => ForceCloseWorkShiftRequest::rulesFor()['reason']],
            [],
            ['forceCloseReason' => __('time.force_close.fields.reason')],
        );

        try {
            $forceClose->handle(
                $shift,
                $validated['forceCloseReason'],
                (int) Tenancy::id(),
                auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'shift_not_open') {
                $this->addError('forceCloseReason', __('time.force_close.errors.shift_not_open'));
            } else {
                throw $e;
            }

            return;
        }

        $this->closeForceClose();
        session()->flash('time_flash', __('time.presence.force_closed'));
    }
}
