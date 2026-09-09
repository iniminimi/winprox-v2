<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyRequiredBreakToWorkShiftAction
{
    public function __construct(
        private ResolveWorkShiftBreakMinutesAction $resolveBreakMinutes,
        private AuditRecorder $audit,
    ) {}

    public function handle(WorkShift $shift, int $tenantId, ?int $actorUserId): WorkShift
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

        return DB::transaction(function () use ($shift, $tenantId, $actorUserId) {
            $locked = WorkShift::query()
                ->whereKey($shift->id)
                ->lockForUpdate()
                ->with('team')
                ->first();

            if ($locked === null || $locked->status->isOpen()) {
                throw new InvalidArgumentException('shift_still_open');
            }

            if ($locked->clock_out_at === null) {
                throw new InvalidArgumentException('shift_missing_clock_out');
            }

            $required = (int) ($locked->team?->required_break_minutes ?? 0);
            if ($required <= 0) {
                throw new InvalidArgumentException('no_team_minimum');
            }

            $before = (int) $locked->total_break_minutes;
            $resolved = $this->resolveBreakMinutes->handle($locked, $before, $locked->clock_out_at);

            if ($resolved <= $before) {
                $durationMinutes = max(0, (int) $locked->clock_in_at->diffInMinutes($locked->clock_out_at));
                if ($durationMinutes <= $required) {
                    throw new InvalidArgumentException('shift_too_short');
                }

                throw new InvalidArgumentException('already_applied');
            }

            $locked->update(['total_break_minutes' => $resolved]);
            $locked = $locked->fresh(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'work_shift.required_break_applied',
                modelType: WorkShift::class,
                modelId: $locked->id,
                payload: [
                    'work_shift_id' => $locked->id,
                    'worker_id' => $locked->worker_id,
                    'internal_team_id' => $locked->internal_team_id,
                    'before_break_minutes' => $before,
                    'after_break_minutes' => (int) $locked->total_break_minutes,
                    'required_break_minutes' => $required,
                ],
            );

            return $locked;
        });
    }
}
