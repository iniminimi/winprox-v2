<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Support\Time\TimeModuleAccess;

class CountPendingAbsenceRequestsAction
{
    public function handle(int $tenantId): int
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        return AbsenceRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', AbsenceRequestStatus::Pending)
            ->count();
    }
}
