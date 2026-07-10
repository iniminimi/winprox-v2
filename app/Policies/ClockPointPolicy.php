<?php

namespace App\Policies;

use App\Models\ClockPoint;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Platform\SuperuserTenantAccess;

class ClockPointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, ClockPoint $clockPoint): bool
    {
        return $this->sameTenant($user, (int) $clockPoint->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, ClockPoint $clockPoint): bool
    {
        return $this->sameTenant($user, (int) $clockPoint->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function renewQr(User $user, ClockPoint $clockPoint): bool
    {
        return $this->update($user, $clockPoint);
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, $tenantId);
    }
}
