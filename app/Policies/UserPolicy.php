<?php

namespace App\Policies;

use App\Models\User;

/**
 * Collega-gebruikers en organisatie (admin-only).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
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

    private function isTenantAdmin(User $user): bool
    {
        return $user->tenant_id !== null && $user->isAdmin();
    }
}
