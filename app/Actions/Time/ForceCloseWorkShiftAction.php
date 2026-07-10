<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftEnded;
use App\Models\WorkShift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ForceCloseWorkShiftAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private CloseOpenWorkShiftTaskLogsAction $closeOpenTaskLogs,
    ) {}

    public function handle(WorkShift $shift, int $tenantId, ?int $actorUserId): WorkShift
    {
        if ((int) $shift->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! $shift->status->isOpen()) {
            throw new InvalidArgumentException('shift_not_open');
        }

        return DB::transaction(function () use ($shift, $actorUserId) {
            $locked = WorkShift::query()
                ->whereKey($shift->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->with(['openBreak', 'breaks', 'worker'])
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            if ($locked->openBreak !== null) {
                $this->endWorkBreak->handle($locked->worker, $locked);
                $locked = $locked->fresh(['openBreak', 'breaks']);
            }

            $totalBreakMinutes = (int) $locked->breaks->sum(fn ($break) => $break->durationMinutes());

            $locked->update([
                'status' => WorkShiftStatus::ForceClosed,
                'clock_out_at' => now(),
                'clock_out_source' => ClockSource::Admin,
                'clock_out_clock_point_id' => $locked->clock_out_clock_point_id ?? $locked->clock_in_clock_point_id,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            $locked = $locked->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);
            $this->closeOpenTaskLogs->handle($locked, $locked->clock_out_at);

            event(new TimeShiftEnded($locked, $actorUserId));

            return $locked;
        });
    }
}
