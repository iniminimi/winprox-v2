<?php

namespace App\Policies;

use App\Models\EsgIndicator;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Esg\EsgModuleAccess;
use App\Support\Tenancy;

class EsgIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user) && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EsgIndicator $indicator): bool
    {
        return $this->viewAny($user)
            && $this->indicatorBelongsToActiveTenant($user, $indicator);
    }

    public function deactivate(User $user, EsgIndicator $indicator): bool
    {
        return $this->update($user, $indicator);
    }

    private function moduleEnabledForUser(User $user): bool
    {
        $tenant = $this->resolveTenant($user);

        return EsgModuleAccess::tenantHasModule($tenant);
    }

    private function indicatorBelongsToActiveTenant(User $user, EsgIndicator $indicator): bool
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        return $tenantId !== null && (int) $indicator->tenant_id === (int) $tenantId;
    }

    private function resolveTenant(User $user): ?Tenant
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
