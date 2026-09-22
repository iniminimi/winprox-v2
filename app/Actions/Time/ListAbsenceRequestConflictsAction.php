<?php

namespace App\Actions\Time;

use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;

class ListAbsenceRequestConflictsAction
{
    /**
     * @return Collection<int, PlannedShift>
     */
    public function handle(AbsenceRequest $request): Collection
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $request->tenant_id);

        return PlannedShift::query()
            ->with(['shiftType'])
            ->where('tenant_id', $request->tenant_id)
            ->where('worker_id', $request->worker_id)
            ->whereDate('work_date', '>=', $request->date_from->toDateString())
            ->whereDate('work_date', '<=', $request->date_to->toDateString())
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();
    }
}
