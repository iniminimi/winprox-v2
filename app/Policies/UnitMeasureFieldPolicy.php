<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitMeasureField;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class UnitMeasureFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, UnitMeasureField $field): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $field->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function update(User $user, UnitMeasureField $field): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $field->tenant_id);
    }
}
