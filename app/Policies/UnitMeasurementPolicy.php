<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitMeasurement;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class UnitMeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, UnitMeasurement $measurement): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $measurement->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }
}
