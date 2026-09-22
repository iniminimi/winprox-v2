<?php

namespace App\Actions\Time;

use App\Models\AbsenceRequest;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ListWorkerAbsenceRequestsAction
{
    /**
     * @return Collection<int, AbsenceRequest>
     */
    public function handle(Worker $worker, int $tenantId): Collection
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        return AbsenceRequest::query()
            ->with(['shiftType'])
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $worker->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }
}
