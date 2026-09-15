<?php

namespace App\Policies;

use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class PlannedShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->staffWithModule($user);
    }

    public function view(User $user, PlannedShift $plannedShift): bool
    {
        return $this->staffWithModule($user)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $plannedShift->tenant_id);
    }

    public function update(User $user, PlannedShift|string|null $plannedShift = null): bool
    {
        if ($plannedShift instanceof PlannedShift) {
            return $this->staffWithModule($user)
                && SuperuserTenantAccess::canAccessTenant($user, (int) $plannedShift->tenant_id);
        }

        return $this->staffWithModule($user);
    }

    public function publish(User $user, PlannedShift|string|null $plannedShift = null): bool
    {
        return $this->staffWithModule($user);
    }

    private function staffWithModule(User $user): bool
    {
        if (! TimeModuleAccess::tenantHasModule($this->resolveTenant($user))) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee() || $user->is_superuser;
    }

    private function resolveTenant(User $user): ?Tenant
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
