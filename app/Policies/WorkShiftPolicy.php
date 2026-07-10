<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkShift;
use App\Support\Platform\SuperuserTenantAccess;

class WorkShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, WorkShift $workShift): bool
    {
        return $this->sameTenant($user, (int) $workShift->tenant_id);
    }

    public function forceClose(User $user, WorkShift $workShift): bool
    {
        return $this->sameTenant($user, (int) $workShift->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function correct(User $user, WorkShift $workShift): bool
    {
        return $this->sameTenant($user, (int) $workShift->tenant_id)
            && $user->isAdmin()
            && ! $workShift->status->isOpen();
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, $tenantId);
    }
}
