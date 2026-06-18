<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
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

        return $task->issue?->isApproved() ?? false;
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
}
