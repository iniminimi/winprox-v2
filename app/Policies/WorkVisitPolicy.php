<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkVisit;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;

class WorkVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->visitsEnabledForUser($user)
            && ($user->is_superuser || $user->tenant_id !== null);
    }

    public function view(User $user, WorkVisit $workVisit): bool
    {
        return $this->visitsEnabledForUser($user)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $workVisit->tenant_id);
    }

    private function visitsEnabledForUser(User $user): bool
    {
        $tenant = $this->resolveTenant($user);

        return $tenant !== null && $tenant->allowsGpsWorkVisits();
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
