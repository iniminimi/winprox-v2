<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitMeasurement;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenant\TenantWorkMenuAccess;

class UnitMeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuUnitMeasurementsEnabledFor($user);
    }

    public function view(User $user, UnitMeasurement $measurement): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $measurement->tenant_id);
    }

    public function create(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuUnitMeasurementsEnabledFor($user);
    }

    private function workMenuUnitMeasurementsEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::unitMeasurementsEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantUnitMeasurementsEnabled();
        }

        return false;
    }
}
