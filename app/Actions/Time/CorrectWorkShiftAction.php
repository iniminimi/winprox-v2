<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use App\Support\Audit\AuditRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrectWorkShiftAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{clock_in_at: string, clock_out_at: string, total_break_minutes: int, reason: string}  $data
     */
    public function handle(WorkShift $shift, array $data, int $tenantId, ?int $actorUserId): WorkShift
    {
        if ((int) $shift->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if ($shift->status->isOpen()) {
            throw new InvalidArgumentException('shift_still_open');
        }

        if ($shift->clock_out_at === null) {
            throw new InvalidArgumentException('shift_missing_clock_out');
        }

        $clockInAt = Carbon::parse($data['clock_in_at']);
        $clockOutAt = Carbon::parse($data['clock_out_at']);
        $totalBreakMinutes = (int) $data['total_break_minutes'];
        $reason = trim((string) $data['reason']);

        if ($clockOutAt->lessThanOrEqualTo($clockInAt)) {
            throw new InvalidArgumentException('clock_out_before_clock_in');
        }

        $durationMinutes = (int) $clockInAt->diffInMinutes($clockOutAt);
        if ($totalBreakMinutes >= $durationMinutes) {
            throw new InvalidArgumentException('break_exceeds_duration');
        }

        return DB::transaction(function () use ($shift, $clockInAt, $clockOutAt, $totalBreakMinutes, $reason, $tenantId, $actorUserId) {
            $locked = WorkShift::query()
                ->whereKey($shift->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status->isOpen()) {
                throw new InvalidArgumentException('shift_still_open');
            }

            $before = [
                'clock_in_at' => $locked->clock_in_at?->toIso8601String(),
                'clock_out_at' => $locked->clock_out_at?->toIso8601String(),
                'total_break_minutes' => (int) $locked->total_break_minutes,
            ];

            $locked->update([
                'clock_in_at' => $clockInAt,
                'clock_out_at' => $clockOutAt,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            $locked = $locked->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'work_shift.corrected',
                modelType: WorkShift::class,
                modelId: $locked->id,
                payload: [
                    'work_shift_id' => $locked->id,
                    'worker_id' => $locked->worker_id,
                    'reason' => $reason,
                    'before' => $before,
                    'after' => [
                        'clock_in_at' => $locked->clock_in_at?->toIso8601String(),
                        'clock_out_at' => $locked->clock_out_at?->toIso8601String(),
                        'total_break_minutes' => (int) $locked->total_break_minutes,
                    ],
                ],
            );

            return $locked;
        });
    }
}
