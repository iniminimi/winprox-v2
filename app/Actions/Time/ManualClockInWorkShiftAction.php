<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManualClockInWorkShiftAction
{
    public function __construct(
        private ClockInAction $clockIn,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds  null = alle vestigingen
     */
    public function handle(
        Worker $worker,
        ClockPoint $clockPoint,
        string $reason,
        int $tenantId,
        ?int $actorUserId,
        ?array $actorLocationIds = null,
    ): WorkShift {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('reason_required');
        }

        if ((int) $worker->tenant_id !== $tenantId || (int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $clockPointLocationId = $clockPoint->location_id !== null ? (int) $clockPoint->location_id : null;
        if ($actorLocationIds !== null) {
            if ($clockPointLocationId === null || ! in_array($clockPointLocationId, $actorLocationIds, true)) {
                throw new InvalidArgumentException('clock_point_not_allowed');
            }
        }

        return DB::transaction(function () use ($worker, $clockPoint, $reason, $tenantId, $actorUserId) {
            $shift = $this->clockIn->handle(
                $worker,
                $clockPoint,
                source: ClockSource::Admin,
            );

            if ($shift->clock_in_device_id !== null) {
                throw new InvalidArgumentException('device_must_be_empty');
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'work_shift.manual_clock_in',
                modelType: WorkShift::class,
                modelId: $shift->id,
                payload: [
                    'work_shift_id' => $shift->id,
                    'worker_id' => $shift->worker_id,
                    'clock_point_id' => $clockPoint->id,
                    'reason' => $reason,
                    'clock_in_at' => $shift->clock_in_at?->toIso8601String(),
                    'clock_in_source' => ClockSource::Admin->value,
                ],
            );

            return $shift;
        });
    }
}
