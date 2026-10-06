<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitCheck;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenant\TenantWorkMenuAccess;

class UnitCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuUnitChecksEnabledFor($user);
    }

    public function view(User $user, UnitCheck $unitCheck): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $unitCheck->tenant_id);
    }

    private function workMenuUnitChecksEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::unitChecksEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantUnitChecksEnabled();
        }

        return false;
    }
}
