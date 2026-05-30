<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasTenantAccess($user);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function approve(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function update(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id)
            && ($user->isAdmin() || $user->isEmployee());
    }

    private function hasTenantAccess(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, $tenantId);
    }
}
