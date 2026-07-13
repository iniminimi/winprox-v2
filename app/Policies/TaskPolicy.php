<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Tenant;
use App\Support\Esg\EsgModuleAccess;
use App\Support\Platform\SuperuserTenantAccess;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Task $task): bool
    {
        if (! $this->sameTenant($user, (int) $task->tenant_id)) {
            return false;
        }

        $task->loadMissing('issue');

        if ($task->issue?->isApproved()) {
            return true;
        }

        return $this->canViewEsgMeasurementTask($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, Task $task): bool
    {
        if (! $this->sameTenant($user, (int) $task->tenant_id)
            || ! ($user->isAdmin() || $user->isEmployee())) {
            return false;
        }

        $task->loadMissing('issue');

        return $task->issue?->isApproved() ?? false;
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
