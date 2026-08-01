<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitCheckList;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class UnitCheckListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, UnitCheckList $list): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $list->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, UnitCheckList $list): bool
    {
        return $this->view($user, $list)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function delete(User $user, UnitCheckList $list): bool
    {
        return $this->update($user, $list);
    }
}
