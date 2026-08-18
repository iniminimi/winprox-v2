<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
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

    /** Portaal-achtergrond, eigen portaal-stijl, QR-stickers — beheerder én medewerker. */
    public function updateTenantBranding(User $user, Tenant $tenant): bool
    {
        return $this->isTenantMemberFor($user, $tenant);
    }

    public function exportTenantData(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    public function requestTenantPurge(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    public function cancelTenantPurge(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant)
            || ($user->is_superuser && SuperuserTenantAccess::canAccessTenant($user, (int) $tenant->id));
    }

    public function executeTrialTenantPurge(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    public function executePaidTenantPurge(User $user, Tenant $tenant): bool
    {
        return $user->is_superuser && SuperuserTenantAccess::canAccessTenant($user, (int) $tenant->id);
    }

    public function applyStarterPack(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant)
            || ($user->is_superuser && SuperuserTenantAccess::canAccessTenant($user, (int) $tenant->id));
    }

    public function removeStarterPack(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant)
            || ($user->is_superuser && SuperuserTenantAccess::canAccessTenant($user, (int) $tenant->id));
    }

    private function isTenantAdminFor(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $tenant->id
            && $user->isAdmin();
    }

    private function isTenantMemberFor(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $tenant->id
            && ($user->isAdmin() || $user->isEmployee());
    }
}
