<?php

namespace App\Actions\Time;

use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExportWorkShiftsAction
{
    /**
     * @return Collection<int, WorkShift>
     */
    public function handle(
        int $tenantId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $teamId = null,
        ?int $workerId = null,
        ?int $clockPointId = null,
    ): Collection {
        return WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->when($from, fn ($q) => $q->where('clock_in_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('clock_in_at', '<=', $to->copy()->endOfDay()))
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($workerId, fn ($q) => $q->where('worker_id', $workerId))
            ->when($clockPointId, fn ($q) => $q->where('clock_in_clock_point_id', $clockPointId))
            ->with(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint'])
            ->orderByDesc('clock_in_at')
            ->get();
    }
}
