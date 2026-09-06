<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftEnded;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClockOutAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private CloseOpenWorkShiftTaskLogsAction $closeOpenTaskLogs,
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
        private AssertWorkerClockDeviceAction $assertClockDevice,
    ) {}

    public function handle(
        Worker $worker,
        ClockPoint $clockPoint,
        ?\Carbon\Carbon $clientTimestamp = null,
        ClockSource $source = ClockSource::ClockPointQr,
        bool $enforceClockDevice = false,
        ?WorkerDevice $device = null,
        ?string $requestDeviceToken = null,
    ): WorkShift {
        if ((int) $worker->tenant_id !== (int) $clockPoint->tenant_id) {
            throw new InvalidArgumentException('worker_clock_point_tenant_mismatch');
        }

        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        if ($enforceClockDevice) {
            $this->assertClockDevice->handle($worker, $device, $requestDeviceToken);
        }

        return DB::transaction(function () use ($worker, $clockPoint, $clientTimestamp, $source) {
            $shift = $this->lockOpenShift($worker);
            $shift->load('openBreak');

            if ($shift->openBreak !== null) {
                $this->endWorkBreak->handle($worker, $shift);
                $shift = $shift->fresh(['breaks']);
            }

            $totalBreakMinutes = (int) $shift->breaks->sum(fn ($break) => $break->durationMinutes());

            $shift->update([
                'status' => WorkShiftStatus::Closed,
                'clock_out_at' => now(),
                'clock_out_client_at' => $clientTimestamp,
                'clock_out_source' => $source,
                'clock_out_clock_point_id' => $clockPoint->id,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            $shift = $shift->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);
            $this->closeOpenTaskLogs->handle($shift, $shift->clock_out_at);

            event(new TimeShiftEnded($shift));
            $this->enqueuePresence->handle(PresenceSourceEvent::ClockOut, $shift);

            return $shift;
        });
    }

    private function lockOpenShift(Worker $worker): WorkShift
    {
        Worker::query()->whereKey($worker->id)->lockForUpdate()->first();

        $shift = WorkShift::query()
            ->where('worker_id', $worker->id)
            ->where('status', WorkShiftStatus::Open)
            ->lockForUpdate()
            ->with('openBreak')
            ->first();

        if ($shift === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        return $shift;
    }
}
