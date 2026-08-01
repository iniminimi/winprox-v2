<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitCheck;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class UnitCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, UnitCheck $unitCheck): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $unitCheck->tenant_id);
    }
}
