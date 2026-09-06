<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;
use App\Support\Platform\SuperuserTenantAccess;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Worker $worker): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $worker->tenant_id);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->view($user, $worker)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function clearClockDevice(User $user, Worker $worker): bool
    {
        return $this->update($user, $worker);
    }

    public function clearClockPin(User $user, Worker $worker): bool
    {
        return $this->update($user, $worker);
    }
}
