<?php

namespace App\Livewire\Time;

use App\Actions\Time\FindOpenWorkShiftForWorkerAction;
use App\Actions\Time\PunchBackofficeSessionAction;
use App\Enums\BackofficePunch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Component;

class BackofficeClock extends Component
{
    use AuthorizesRequests;

    public string $errorMessage = '';

    public function clock(PunchBackofficeSessionAction $punch, FindOpenWorkShiftForWorkerAction $findShift): void
    {
        $open = $this->openShift($findShift);

        $this->punch($punch, $open !== null ? BackofficePunch::ClockOut : BackofficePunch::ClockIn);
    }

    public function toggleBreak(PunchBackofficeSessionAction $punch, FindOpenWorkShiftForWorkerAction $findShift): void
    {
        $open = $this->openShift($findShift);
        if ($open === null) {
            $this->errorMessage = __('time.backoffice_clock.errors.not_in');

            return;
        }

        $this->punch($punch, $open->openBreak !== null ? BackofficePunch::EndBreak : BackofficePunch::StartBreak);
    }

    public function render(FindOpenWorkShiftForWorkerAction $findShift)
    {
        $user = auth()->user();
        $tenant = $user?->tenant;
        $visible = $user instanceof User
            && $tenant instanceof Tenant
            && $user->can('clockFromBackoffice', $tenant);
        $shift = $visible ? $this->openShift($findShift) : null;

        return view('livewire.time.backoffice-clock', [
            'visible' => $visible,
            'clockedIn' => $shift !== null,
            'onBreak' => $shift?->openBreak !== null,
        ]);
    }

    private function punch(PunchBackofficeSessionAction $punch, BackofficePunch $kind): void
    {
        $user = auth()->user();
        $tenant = $user?->tenant;
        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('clockFromBackoffice', $tenant);
        $this->errorMessage = '';

        try {
            $punch->handle($tenant, $user, $kind);
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = match ($e->getMessage()) {
                'backoffice_clock_disabled' => __('time.backoffice_clock.errors.disabled'),
                'backoffice_worker_missing' => __('time.backoffice_clock.errors.no_worker'),
                'backoffice_clock_point_missing' => __('time.backoffice_clock.errors.no_clock_point'),
                'shift_already_open' => __('time.backoffice_clock.errors.already_in'),
                'shift_not_open' => __('time.backoffice_clock.errors.not_in'),
                'break_already_open' => __('time.backoffice_clock.errors.break_open'),
                'break_not_open' => __('time.backoffice_clock.errors.break_closed'),
                default => throw $e,
            };
        }
    }

    private function openShift(FindOpenWorkShiftForWorkerAction $findShift): ?\App\Models\WorkShift
    {
        $worker = auth()->user()?->linkedWorker;

        return $worker !== null ? $findShift->handle($worker) : null;
    }
}
