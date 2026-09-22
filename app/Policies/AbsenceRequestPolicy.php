<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class AbsenceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->staffWithModule($user);
    }

    public function view(User $user, AbsenceRequest $absenceRequest): bool
    {
        return $this->staffWithModule($user)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $absenceRequest->tenant_id);
    }

    public function decide(User $user, AbsenceRequest $absenceRequest): bool
    {
        return $this->view($user, $absenceRequest);
    }

    private function staffWithModule(User $user): bool
    {
        if (! TimeModuleAccess::tenantHasModule($this->resolveTenant($user))) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee() || $user->is_superuser;
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
