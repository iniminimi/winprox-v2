<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftStarted;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClockInAction
{
    public function __construct(
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
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
                ->first();

            if ($existing !== null) {
                throw new InvalidArgumentException('shift_already_open');
            }

            $shift = WorkShift::create([
                'tenant_id' => $worker->tenant_id,
                'worker_id' => $worker->id,
                'internal_team_id' => $worker->internal_team_id,
                'clock_in_clock_point_id' => $clockPoint->id,
                'presence_clock_point_id' => $clockPoint->id,
                'status' => WorkShiftStatus::Open,
                'clock_in_at' => now(),
                'clock_in_client_at' => $clientTimestamp,
                'clock_in_source' => $source,
                'clock_in_device_id' => $device?->id,
            ]);

            $shift = $shift->fresh(['worker', 'team', 'clockInClockPoint', 'presenceClockPoint', 'openBreak']);
            event(new TimeShiftStarted($shift));
            $this->enqueuePresence->handle(PresenceSourceEvent::ClockIn, $shift);

            return $shift;
        });
    }
}
