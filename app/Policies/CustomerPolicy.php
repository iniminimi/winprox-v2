<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $customer->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
