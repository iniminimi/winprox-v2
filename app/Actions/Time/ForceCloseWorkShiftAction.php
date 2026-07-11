<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Events\Time\TimeShiftEnded;
use App\Models\WorkShift;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ForceCloseWorkShiftAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private CloseOpenWorkShiftTaskLogsAction $closeOpenTaskLogs,
        private AuditRecorder $audit,
    ) {}

    public function handle(WorkShift $shift, string $reason, int $tenantId, ?int $actorUserId): WorkShift
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('reason_required');
        }

        if ((int) $shift->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! $shift->status->isOpen()) {
            throw new InvalidArgumentException('shift_not_open');
        }

        return DB::transaction(function () use ($shift, $reason, $tenantId, $actorUserId) {
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

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'work_shift.force_closed',
                modelType: WorkShift::class,
                modelId: $locked->id,
                payload: [
                    'work_shift_id' => $locked->id,
                    'worker_id' => $locked->worker_id,
                    'internal_team_id' => $locked->internal_team_id,
                    'reason' => $reason,
                    'clock_in_at' => $locked->clock_in_at->toIso8601String(),
                    'force_closed_at' => $locked->clock_out_at?->toIso8601String(),
                    'total_break_minutes' => (int) $locked->total_break_minutes,
                    'clock_in_clock_point_id' => $locked->clock_in_clock_point_id,
                ],
            );

            event(new TimeShiftEnded($locked, $actorUserId));

            return $locked;
        });
    }
}
