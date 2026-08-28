<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitMeasureField;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenant\TenantWorkMenuAccess;

class UnitMeasureFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuUnitMeasurementsEnabledFor($user);
    }

    public function view(User $user, UnitMeasureField $field): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $field->tenant_id);
    }

    public function create(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuUnitMeasurementsEnabledFor($user);
    }

    public function update(User $user, UnitMeasureField $field): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $field->tenant_id);
    }

    private function workMenuUnitMeasurementsEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::unitMeasurementsEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantUnitMeasurementsEnabled();
        }

        return false;
    }
}
