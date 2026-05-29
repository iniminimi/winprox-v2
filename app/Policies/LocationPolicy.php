<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Location $location): bool
    {
        return $user->is_superuser || (int) $user->tenant_id === (int) $location->tenant_id;
    }
}
