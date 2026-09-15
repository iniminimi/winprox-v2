<?php

namespace App\Actions\Time;

use App\Models\ShiftType;
use App\Support\Time\TimeModuleAccess;

class ListShiftTypesAction
{
    /**
     * @return list<ShiftType>
     */
    public function handle(int $tenantId, bool $activeOnly = false): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        return ShiftType::query()
            ->where('tenant_id', $tenantId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get()
            ->all();
    }
}
