<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }

    public function delete(User $user, Category $category): bool
    {
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->isAdmin();
    }

    public function syncTeams(User $user, Category $category): bool
    {
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }
}
