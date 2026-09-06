<?php

namespace App\Actions\Time;

use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EndWorkBreakAction
{
    public function __construct(
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
        private AssertWorkerClockDeviceAction $assertClockDevice,
    ) {}

    public function handle(
        Worker $worker,
        WorkShift $shift,
        bool $enforceClockDevice = false,
        ?WorkerDevice $device = null,
        ?string $requestDeviceToken = null,
    ): WorkBreak {
        if ((int) $worker->id !== (int) $shift->worker_id) {
            throw new InvalidArgumentException('shift_worker_mismatch');
        }

        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        if ($enforceClockDevice) {
            $this->assertClockDevice->handle(
                $worker,
                $device,
                (int) $worker->tenant_id,
                $requestDeviceToken,
            );
        }

        return DB::transaction(function () use ($worker, $shift) {
            $shift = WorkShift::query()
                ->whereKey($shift->id)
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($shift === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            $openBreak = WorkBreak::query()
                ->where('work_shift_id', $shift->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($openBreak === null) {
                throw new InvalidArgumentException('break_not_open');
            }

            $openBreak->update(['ended_at' => now()]);
            $break = $openBreak->fresh();

            $this->enqueuePresence->handle(PresenceSourceEvent::BreakEnd, $shift, $break);

            return $break;
        });
    }
}
