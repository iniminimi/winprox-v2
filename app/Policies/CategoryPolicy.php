<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Category $category): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $category->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        if ($user->is_superuser) {
            return false;
        }

        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }

    public function delete(User $user, Category $category): bool
    {
        if ($user->is_superuser) {
            return false;
        }

        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            return false;
        }

        return $user->isAdmin();
    }

    public function syncTeams(User $user, Category $category): bool
    {
        if ($user->is_superuser) {
            return false;
        }

        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }
}
