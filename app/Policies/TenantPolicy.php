<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use App\Support\Platform\SuperuserTenantAccess;

class TenantPolicy
{
    public function manageSubscription(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    public function manageOrganisation(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    private function isTenantAdminFor(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $tenant->id
            && $user->isAdmin();
    }
}
