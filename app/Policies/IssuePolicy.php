<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;
use App\Support\Tenant\TenantWorkMenuAccess;

class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasTenantAccess($user)
            && ($this->workMenuIssuesTasksEnabledFor($user)
                || $this->workMenuInspectionRoundsEnabledFor($user));
    }

    public function viewIssues(User $user): bool
    {
        return $this->hasTenantAccess($user)
            && $this->workMenuIssuesTasksEnabledFor($user);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id)
            && $this->mayUseIssue($user, $issue);
    }

    public function create(User $user): bool
    {
        if ($user->is_superuser) {
            return Tenancy::id() !== null
                && TenantWorkMenuAccess::activeTenantIssuesTasksEnabled();
        }

        return ($user->isAdmin() || $user->isEmployee())
            && $this->workMenuIssuesTasksEnabledFor($user);
    }

    public function approve(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id)
            && ($user->isAdmin() || $user->isEmployee())
            && $this->mayUseIssue($user, $issue);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $this->sameTenant($user, $issue->tenant_id)
            && ($user->isAdmin() || $user->isEmployee())
            && $this->mayUseIssue($user, $issue);
    }

    public function viewInspectionRounds(User $user): bool
    {
        return $this->hasTenantAccess($user)
            && $this->workMenuInspectionRoundsEnabledFor($user);
    }

    public function createInspectionRound(User $user): bool
    {
        if ($user->is_superuser) {
            return Tenancy::id() !== null
                && $this->workMenuInspectionRoundsEnabledFor($user);
        }

        return ($user->isAdmin() || $user->isEmployee())
            && $this->workMenuInspectionRoundsEnabledFor($user);
    }

    public function manageInspectionRoundFavorite(User $user, Issue $issue): bool
    {
        return $this->update($user, $issue)
            && $issue->isInspectionRound()
            && $this->workMenuInspectionRoundsEnabledFor($user);
    }

    private function mayUseIssue(User $user, Issue $issue): bool
    {
        if ($issue->isInspectionRound()) {
            return $this->workMenuIssuesTasksEnabledFor($user)
                || $this->workMenuInspectionRoundsEnabledFor($user);
        }

        return $this->workMenuIssuesTasksEnabledFor($user);
    }

    private function workMenuIssuesTasksEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::issuesTasksEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantIssuesTasksEnabled();
        }

        return false;
    }

    private function workMenuInspectionRoundsEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::inspectionRoundsEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantInspectionRoundsEnabled();
        }

        return false;
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
