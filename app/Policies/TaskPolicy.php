<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Tenant;
use App\Support\Esg\EsgModuleAccess;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenant\TenantWorkMenuAccess;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasTenantAccess($user)
            && $this->workMenuIssuesTasksEnabledFor($user);
    }

    public function view(User $user, Task $task): bool
    {
        if (! $this->sameTenant($user, (int) $task->tenant_id)) {
            return false;
        }

        $task->loadMissing('issue');

        if ($task->issue?->isApproved()) {
            return $this->mayUseTask($user, $task);
        }

        return $this->canViewEsgMeasurementTask($user, $task);
    }

    public function create(User $user): bool
    {
        return ($user->isAdmin() || $user->isEmployee())
            && $this->workMenuIssuesTasksEnabledFor($user);
    }

    public function update(User $user, Task $task): bool
    {
        if (! $this->sameTenant($user, (int) $task->tenant_id)
            || ! ($user->isAdmin() || $user->isEmployee())) {
            return false;
        }

        $task->loadMissing('issue');

        return ($task->issue?->isApproved() ?? false)
            && $this->mayUseTask($user, $task);
    }

    private function mayUseTask(User $user, Task $task): bool
    {
        if ($task->issue?->isInspectionRound()) {
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

    private function canViewEsgMeasurementTask(User $user, Task $task): bool
    {
        if (! $user->isAdmin() || $task->issue?->esg_indicator_id === null) {
            return false;
        }

        return EsgModuleAccess::tenantHasModule(
            Tenant::query()->find((int) $task->tenant_id),
        );
    }
}
