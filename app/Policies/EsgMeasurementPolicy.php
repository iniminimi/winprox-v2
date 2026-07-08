<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EsgMeasurement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Esg\EsgModuleAccess;
use App\Support\Tenancy;

class EsgMeasurementPolicy
{
    public function create(User $user): bool
    {
        return $this->moduleEnabledForUser($user) && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function view(User $user, EsgMeasurement $measurement): bool
    {
        return $this->create($user)
            && $this->measurementBelongsToActiveTenant($user, $measurement);
    }

    private function moduleEnabledForUser(User $user): bool
    {
        $tenant = $this->resolveTenant($user);

        return EsgModuleAccess::tenantHasModule($tenant);
    }

    private function measurementBelongsToActiveTenant(User $user, EsgMeasurement $measurement): bool
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        return $tenantId !== null && (int) $measurement->tenant_id === (int) $tenantId;
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
