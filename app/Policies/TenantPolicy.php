<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenant\TenantWorkMenuAccess;

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

    public function manageWorkMenu(User $user, Tenant $tenant): bool
    {
        return $this->isTenantAdminFor($user, $tenant);
    }

    public function accessWorkMenuCalendar(User $user, Tenant $tenant): bool
    {
        return $this->isTenantMemberFor($user, $tenant)
            && TenantWorkMenuAccess::calendarEnabled($tenant);
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

    /** Nooit-gebruikt of vals account direct wissen (platformbeheer, geen support view nodig). */
    public function deleteUnusedTenant(User $user, Tenant $tenant): bool
    {
        return $user->is_superuser;
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

    public function dismissStarterPackResult(User $user, Tenant $tenant): bool
    {
        return $this->applyStarterPack($user, $tenant);
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
