<?php

namespace App\Policies;

use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class ClockPointPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function view(User $user, ClockPoint $clockPoint): bool
    {
        return $this->sameTenant($user, (int) $clockPoint->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ClockPoint $clockPoint): bool
    {
        return $this->moduleEnabledForUser($user)
            && $this->sameTenant($user, (int) $clockPoint->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function renewQr(User $user, ClockPoint $clockPoint): bool
    {
        return $this->update($user, $clockPoint);
    }

    private function moduleEnabledForUser(User $user): bool
    {
        return TimeModuleAccess::tenantHasModule($this->resolveTenant($user));
    }

    private function resolveTenant(User $user): ?Tenant
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, $tenantId);
    }
}
