<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IotSensor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Iot\IotModuleAccess;
use App\Support\Tenancy;

class IotSensorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user) && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, IotSensor $sensor): bool
    {
        return $this->viewAny($user)
            && $this->belongsToActiveTenant($user, (int) $sensor->tenant_id);
    }

    private function moduleEnabledForUser(User $user): bool
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;
        if ($tenantId === null) {
            return false;
        }

        return IotModuleAccess::tenantHasModule(Tenant::query()->find($tenantId));
    }

    private function belongsToActiveTenant(User $user, int $tenantId): bool
    {
        $active = Tenancy::id() ?? $user->tenant_id;

        return $active !== null && (int) $active === $tenantId;
    }
}
