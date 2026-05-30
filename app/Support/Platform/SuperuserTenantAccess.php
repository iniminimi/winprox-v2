<?php

namespace App\Support\Platform;

use App\Models\User;
use App\Support\Tenancy;

/**
 * Bepaalt of een superuser een tenant mag benaderen (support view scoping).
 */
final class SuperuserTenantAccess
{
    public static function canAccessTenant(User $user, int $tenantId): bool
    {
        if (! $user->is_superuser || $user->tenant_id !== null) {
            return (int) $user->tenant_id === $tenantId;
        }

        $scoped = Tenancy::id();

        if ($scoped === null) {
            return true;
        }

        return $scoped === $tenantId;
    }
}
