<?php

namespace App\Actions\Time;

use App\Enums\WorkShiftStatus;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Houdt één open shift en verplaatst de aanwezigheid naar dit clock point.
 * Uitzonderlijk (hop tussen vestigingen): geen nieuwe shift, wel een hop-vermelding.
 */
class TransferOpenWorkShiftToClockPointAction
{
    public function __construct(
        private EndWorkBreakAction $endWorkBreak,
        private AssertWorkerClockDeviceAction $assertClockDevice,
    ) {}

    public function handle(
        Worker $worker,
        ClockPoint $clockPoint,
        ?WorkerDevice $device = null,
        ?\Carbon\Carbon $clientTimestamp = null,
        bool $enforceClockDevice = false,
        ?string $requestDeviceToken = null,
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

        if ($enforceClockDevice) {
            $this->assertClockDevice->handle($worker, $device, $requestDeviceToken);
        }

        return DB::transaction(function () use ($worker, $clockPoint) {
            Worker::query()->whereKey($worker->id)->lockForUpdate()->first();

            $existing = WorkShift::query()
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->with(['openBreak', 'clockInClockPoint', 'presenceClockPoint'])
                ->first();

            if ($existing === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            $fromClockPointId = $existing->currentClockPointId();
            if ($fromClockPointId === (int) $clockPoint->id) {
                throw new InvalidArgumentException('shift_already_open');
            }

            if ($existing->openBreak !== null) {
                $this->endWorkBreak->handle($worker, $existing);
                $existing = $existing->fresh(['openBreak', 'clockInClockPoint', 'presenceClockPoint']);
            }

            $fromPoint = ClockPoint::query()->find($fromClockPointId);
            $hops = is_array($existing->location_hops) ? $existing->location_hops : [];
            $hops[] = [
                'at' => now()->toIso8601String(),
                'from_clock_point_id' => $fromClockPointId,
                'from_clock_point_name' => $fromPoint?->name,
                'from_location_id' => $fromPoint?->location_id !== null ? (int) $fromPoint->location_id : null,
                'to_clock_point_id' => (int) $clockPoint->id,
                'to_clock_point_name' => $clockPoint->name,
                'to_location_id' => $clockPoint->location_id !== null ? (int) $clockPoint->location_id : null,
            ];

            $existing->update([
                'presence_clock_point_id' => $clockPoint->id,
                'location_hops' => $hops,
            ]);

            return $existing->fresh(['worker', 'team', 'clockInClockPoint', 'presenceClockPoint', 'openBreak']);
        });
    }
}
