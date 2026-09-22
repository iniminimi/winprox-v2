<?php

namespace App\Actions\Time;

use App\Enums\WorkShiftStatus;
use App\Models\WorkShift;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrectWorkShiftAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{clock_in_at: string, clock_out_at?: string|null, total_break_minutes: int, reason: string}  $data
     */
    public function handle(WorkShift $shift, array $data, int $tenantId, ?int $actorUserId): WorkShift
    {
        if ((int) $shift->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if ($shift->status->isOpen()) {
            throw new InvalidArgumentException('shift_still_open');
        }

        $clockInAt = Carbon::parse($data['clock_in_at']);
        $clockOutRaw = $data['clock_out_at'] ?? null;
        $clockOutAt = ($clockOutRaw === null || $clockOutRaw === '')
            ? null
            : Carbon::parse($clockOutRaw);
        $totalBreakMinutes = (int) $data['total_break_minutes'];
        $reason = trim((string) $data['reason']);

        if ($clockOutAt !== null) {
            if ($clockOutAt->lessThanOrEqualTo($clockInAt)) {
                throw new InvalidArgumentException('clock_out_before_clock_in');
            }

            $durationMinutes = (int) $clockInAt->diffInMinutes($clockOutAt);
            if ($totalBreakMinutes >= $durationMinutes) {
                throw new InvalidArgumentException('break_exceeds_duration');
            }
        }

        return DB::transaction(function () use ($shift, $clockInAt, $clockOutAt, $totalBreakMinutes, $reason, $tenantId, $actorUserId) {
            $locked = WorkShift::query()
                ->whereKey($shift->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status->isOpen()) {
                throw new InvalidArgumentException('shift_still_open');
            }

            if ($clockOutAt === null) {
                Worker::query()->whereKey($locked->worker_id)->lockForUpdate()->first();

                $otherOpen = WorkShift::query()
                    ->where('worker_id', $locked->worker_id)
                    ->where('status', WorkShiftStatus::Open)
                    ->whereKeyNot($locked->id)
                    ->lockForUpdate()
                    ->exists();

                if ($otherOpen) {
                    throw new InvalidArgumentException('worker_has_open_shift');
                }
            }

            $before = [
                'status' => $locked->status->value,
                'clock_in_at' => $locked->clock_in_at?->toIso8601String(),
                'clock_out_at' => $locked->clock_out_at?->toIso8601String(),
                'total_break_minutes' => (int) $locked->total_break_minutes,
            ];

            $locked->update($clockOutAt === null
                ? [
                    'status' => WorkShiftStatus::Open,
                    'clock_in_at' => $clockInAt,
                    'clock_out_at' => null,
                    'clock_out_client_at' => null,
                    'clock_out_source' => null,
                    'clock_out_clock_point_id' => null,
                    'total_break_minutes' => $totalBreakMinutes,
                ]
                : [
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
                        'status' => $locked->status->value,
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
