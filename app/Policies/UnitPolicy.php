<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->is_superuser || (int) $user->tenant_id === (int) $unit->tenant_id;
    }
}
