<?php

namespace App\Actions\Time;

use App\Data\Time\WorkerHoursSnapshot;
use App\Models\WorkShift;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use InvalidArgumentException;

class ListWorkerHoursAction
{
    /** Eigen diensten van één uitvoerder in een periode, nieuwste eerst. */
    public function handle(Worker $worker, int $tenantId, Carbon $from, Carbon $to): WorkerHoursSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $shifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->where('clock_in_at', '>=', $from)
            ->where('clock_in_at', '<=', $to)
            ->with(['clockInClockPoint', 'clockOutClockPoint'])
            ->orderByDesc('clock_in_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $totalNetMinutes = $shifts->sum(fn (WorkShift $shift) => $shift->netWorkMinutes());

        return new WorkerHoursSnapshot(
            shifts: $shifts,
            totalNetMinutes: $totalNetMinutes,
            from: $from,
            to: $to,
        );
    }
}
