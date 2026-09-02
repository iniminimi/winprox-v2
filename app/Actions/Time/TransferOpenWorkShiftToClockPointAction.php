<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftEnded;
use App\Events\Time\TimeShiftStarted;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sluit de open shift elders en opent meteen een nieuwe shift op dit clock point.
 * Gebruik wanneer een uitvoerder al ingeklokt is op een andere vestiging.
 */
class TransferOpenWorkShiftToClockPointAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private CloseOpenWorkShiftTaskLogsAction $closeOpenTaskLogs,
    ) {}

    public function handle(
        Worker $worker,
        ClockPoint $clockPoint,
        ?WorkerDevice $device = null,
        ?\Carbon\Carbon $clientTimestamp = null,
        ClockSource $source = ClockSource::ClockPointQr,
    ): WorkShift {
        if ((int) $worker->tenant_id !== (int) $clockPoint->tenant_id) {
            throw new InvalidArgumentException('worker_clock_point_tenant_mismatch');
        }

        if (! $worker->is_active) {
            throw new InvalidArgumentException('worker_inactive');
        }

        if (! $clockPoint->is_active) {
            throw new InvalidArgumentException('clock_point_inactive');
        }

        $worker->loadMissing(['team', 'locations']);
        if (! $worker->canClockAt($clockPoint->location_id !== null ? (int) $clockPoint->location_id : null)) {
            throw new InvalidArgumentException('worker_location_not_allowed');
        }

        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        return DB::transaction(function () use ($worker, $clockPoint, $device, $clientTimestamp, $source) {
            Worker::query()->whereKey($worker->id)->lockForUpdate()->first();

            $existing = WorkShift::query()
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->with(['openBreak', 'breaks'])
                ->first();

            if ($existing === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            if ((int) $existing->clock_in_clock_point_id === (int) $clockPoint->id) {
                throw new InvalidArgumentException('shift_already_open');
            }

            if ($existing->openBreak !== null) {
                $this->endWorkBreak->handle($worker, $existing);
                $existing = $existing->fresh(['breaks']);
            }

            $totalBreakMinutes = (int) $existing->breaks->sum(fn ($break) => $break->durationMinutes());
            $closedAt = now();

            $existing->update([
                'status' => WorkShiftStatus::Closed,
                'clock_out_at' => $closedAt,
                'clock_out_client_at' => $clientTimestamp,
                'clock_out_source' => ClockSource::Auto,
                'clock_out_clock_point_id' => $clockPoint->id,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            $closed = $existing->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);
            $this->closeOpenTaskLogs->handle($closed, $closedAt);
            event(new TimeShiftEnded($closed));

            $shift = WorkShift::create([
                'tenant_id' => $worker->tenant_id,
                'worker_id' => $worker->id,
                'internal_team_id' => $worker->internal_team_id,
                'clock_in_clock_point_id' => $clockPoint->id,
                'status' => WorkShiftStatus::Open,
                'clock_in_at' => now(),
                'clock_in_client_at' => $clientTimestamp,
                'clock_in_source' => $source,
                'clock_in_device_id' => $device?->id,
            ]);

            event(new TimeShiftStarted($shift->fresh()));

            return $shift->fresh(['worker', 'team', 'clockInClockPoint', 'openBreak']);
        });
    }
}
