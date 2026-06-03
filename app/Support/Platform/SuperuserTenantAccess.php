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
        // Non-superusers can only access their own tenant
        if (! $user->is_superuser) {
            return (int) $user->tenant_id === $tenantId;
        }

        // Superusers with a tenant_id can only access their own tenant
        if ($user->tenant_id !== null) {
            return (int) $user->tenant_id === $tenantId;
        }

        // Superusers without a tenant_id (support) can access any tenant when scoped
        $scoped = Tenancy::id();

        if ($scoped === null) {
            return true;
        }

        return $scoped === $tenantId;
    }
}
