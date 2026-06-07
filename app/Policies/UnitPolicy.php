<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Units\UnitDeletionGuard;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Unit $unit): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $unit->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function updateGps(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function deactivate(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && UnitDeletionGuard::canDelete($unit);
    }
}
