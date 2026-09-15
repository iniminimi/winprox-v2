<?php

namespace App\Policies;

use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class ShiftTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->staffWithModule($user);
    }

    public function create(User $user): bool
    {
        return $this->staffWithModule($user);
    }

    public function update(User $user, ShiftType $shiftType): bool
    {
        return $this->staffWithModule($user)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $shiftType->tenant_id);
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
