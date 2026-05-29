<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

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

    private function hasTenantAccess(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    private function sameTenant(User $user, int $tenantId): bool
    {
        if ($user->is_superuser && $user->tenant_id === null) {
            return true;
        }

        return (int) $user->tenant_id === $tenantId;
    }
}
