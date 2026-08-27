<?php

namespace App\Actions\Time;

use App\Data\Reports\ListExportResult;
use App\Models\WorkShift;
use App\Support\Reports\ListExportLimit;
use Carbon\Carbon;

class ExportWorkShiftsAction
{
    /**
     * @return ListExportResult<WorkShift>
     */
    public function handle(
        int $tenantId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $teamId = null,
        ?int $workerId = null,
        ?int $clockPointId = null,
    ): ListExportResult {
        $limit = ListExportLimit::MAX;

        $rows = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->when($from, fn ($q) => $q->where('clock_in_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('clock_in_at', '<=', $to->copy()->endOfDay()))
            ->when($teamId, fn ($q) => $q->where('internal_team_id', $teamId))
            ->when($workerId, fn ($q) => $q->where('worker_id', $workerId))
            ->when($clockPointId, fn ($q) => $q->where('clock_in_clock_point_id', $clockPointId))
            ->with(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint', 'taskLogs.task'])
            ->orderByDesc('clock_in_at')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
