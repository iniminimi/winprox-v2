<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class WorkShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user)
            && ($user->is_superuser || $user->tenant_id !== null);
    }

    public function view(User $user, WorkShift $workShift): bool
    {
        return $this->moduleEnabledForUser($user)
            && $this->sameTenant($user, (int) $workShift->tenant_id);
    }

    public function forceClose(User $user, WorkShift $workShift): bool
    {
        return $this->view($user, $workShift)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function correct(User $user, WorkShift $workShift): bool
    {
        return $this->sameTenant($user, (int) $workShift->tenant_id)
            && $this->moduleEnabledForUser($user)
            && $user->isAdmin()
            && ! $workShift->status->isOpen();
    }

    public function clockIn(User $user): bool
    {
        return $this->moduleEnabledForUser($user)
            && ($user->is_superuser || $user->tenant_id !== null);
    }

    public function clockOut(User $user, WorkShift $workShift): bool
    {
        return $this->view($user, $workShift);
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
