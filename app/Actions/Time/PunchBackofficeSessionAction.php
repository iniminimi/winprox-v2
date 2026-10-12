<?php

namespace App\Actions\Time;

use App\Enums\BackofficePunch;
use App\Enums\ClockSource;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use InvalidArgumentException;

/**
 * Self punch from the backoffice sidebar (email session). No phone bind, no GPS gate.
 */
class PunchBackofficeSessionAction
{
    public function __construct(
        private ClockInAction $clockIn,
        private ClockOutAction $clockOut,
        private StartWorkBreakAction $startBreak,
        private EndWorkBreakAction $endBreak,
        private FindOpenWorkShiftForWorkerAction $findShift,
    ) {}

    public function handle(Tenant $tenant, User $actor, BackofficePunch $punch): WorkShift
    {
        if ((int) $actor->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! $tenant->allowsBackofficeClock()) {
            throw new InvalidArgumentException('backoffice_clock_disabled');
        }

        $worker = $actor->linkedWorker;
        if (! $worker instanceof Worker || ! $worker->is_active || (int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('backoffice_worker_missing');
        }

        $shift = $this->findShift->handle($worker);

        return match ($punch) {
            BackofficePunch::ClockIn => $this->openShift($worker, $shift),
            BackofficePunch::ClockOut => $this->closeShift($worker, $shift),
            BackofficePunch::StartBreak => $this->openBreak($worker, $shift),
            BackofficePunch::EndBreak => $this->closeBreak($worker, $shift),
        };
    }

    private function openShift(Worker $worker, ?WorkShift $shift): WorkShift
    {
        if ($shift !== null) {
            throw new InvalidArgumentException('shift_already_open');
        }

        return $this->clockIn->handle(
            $worker,
            $this->resolveClockPoint($worker),
            source: ClockSource::Backoffice,
        );
    }

    private function closeShift(Worker $worker, ?WorkShift $shift): WorkShift
    {
        if ($shift === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        return $this->clockOut->handle(
            $worker,
            $this->clockPointForShift($worker, $shift),
            source: ClockSource::Backoffice,
        );
    }

    private function openBreak(Worker $worker, ?WorkShift $shift): WorkShift
    {
        if ($shift === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        $this->startBreak->handle($worker, $shift);

        return $shift->fresh(['openBreak']);
    }

    private function closeBreak(Worker $worker, ?WorkShift $shift): WorkShift
    {
        if ($shift === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        $this->endBreak->handle($worker, $shift);

        return $shift->fresh(['openBreak']);
    }

    private function clockPointForShift(Worker $worker, WorkShift $shift): ClockPoint
    {
        $shift->loadMissing(['presenceClockPoint', 'clockInClockPoint']);
        $current = $shift->presenceClockPoint ?? $shift->clockInClockPoint;
        if ($current instanceof ClockPoint && $current->is_active && (int) $current->tenant_id === (int) $worker->tenant_id) {
            return $current;
        }

        return $this->resolveClockPoint($worker);
    }

    private function resolveClockPoint(Worker $worker): ClockPoint
    {
        $worker->loadMissing(['team', 'locations', 'tenant']);

        $point = ClockPoint::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(function (ClockPoint $clockPoint) use ($worker): bool {
                $locationId = $clockPoint->location_id !== null ? (int) $clockPoint->location_id : null;

                return $worker->canClockAt($locationId);
            });

        if (! $point instanceof ClockPoint) {
            throw new InvalidArgumentException('backoffice_clock_point_missing');
        }

        return $point;
    }
}
