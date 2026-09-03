<?php

namespace App\Policies;

use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;

class PresenceSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->moduleEnabledForUser($user)
            && ($user->is_superuser || $user->tenant_id !== null);
    }

    public function view(User $user, PresenceSubmission $submission): bool
    {
        return $this->moduleEnabledForUser($user)
            && $this->sameTenant($user, (int) $submission->tenant_id);
    }

    public function retry(User $user, PresenceSubmission $submission): bool
    {
        return $this->view($user, $submission)
            && ($user->isAdmin() || $user->isEmployee());
    }

    private function moduleEnabledForUser(User $user): bool
    {
        return TimeModuleAccess::tenantHasModule($this->resolveTenant($user));
    }

    private function resolveTenant(User $user): ?Tenant
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, $tenantId);
    }
}
