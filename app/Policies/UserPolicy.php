<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

/**
 * Collega-gebruikers en organisatie (admin-only).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, User $colleague): bool
    {
        if ($colleague->is_superuser) {
            return false;
        }

        return SuperuserTenantAccess::canAccessTenant($user, (int) $colleague->tenant_id)
            && $colleague->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function update(User $user, User $colleague): bool
    {
        return $this->isTenantAdmin($user)
            && (int) $user->tenant_id === (int) $colleague->tenant_id
            && ! $colleague->is_superuser;
    }

    public function downloadPromoQr(User $user): bool
    {
        return $user->is_superuser;
    }

    public function managePromoRecipients(User $user): bool
    {
        return $user->is_superuser;
    }

    public function managePromoCampaigns(User $user): bool
    {
        return $user->is_superuser;
    }

    public function captureManualScreenshots(User $user): bool
    {
        return $user->is_superuser;
    }

    public function runTranslationSync(User $user): bool
    {
        return $user->is_superuser;
    }

    public function accessPlatform(User $user): bool
    {
        return $user->is_superuser;
    }

    public function viewWhatsNew(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function setPassword(User $actor, User $target): bool
    {
        return $actor->is_superuser && ! $target->is_superuser;
    }

    private function isTenantAdmin(User $user): bool
    {
        return $user->tenant_id !== null && $user->isAdmin();
    }
}
