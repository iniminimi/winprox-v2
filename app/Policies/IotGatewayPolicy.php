<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IotGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Iot\IotModuleAccess;
use App\Support\Tenancy;

class IotGatewayPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user) && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, IotGateway $gateway): bool
    {
        return $this->viewAny($user)
            && $this->belongsToActiveTenant($user, (int) $gateway->tenant_id);
    }

    private function moduleEnabledForUser(User $user): bool
    {
        $tenant = $this->resolveTenant($user);

        return IotModuleAccess::tenantHasModule($tenant);
    }

    private function belongsToActiveTenant(User $user, int $tenantId): bool
    {
        $active = Tenancy::id() ?? $user->tenant_id;

        return $active !== null && (int) $active === $tenantId;
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
