<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HardwareDevice;
use App\Models\User;

class HardwareDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser;
    }

    public function view(User $user, HardwareDevice $device): bool
    {
        return $user->is_superuser;
    }

    public function create(User $user): bool
    {
        return $user->is_superuser;
    }

    public function update(User $user, HardwareDevice $device): bool
    {
        return $user->is_superuser;
    }

    /** Tenant-admin mag sticker-S/N van een gekoppeld scherm lezen (geen mutatie). */
    public function viewSerialForTenant(User $user, HardwareDevice $device): bool
    {
        if ($user->is_superuser) {
            return true;
        }

        return $user->tenant_id !== null
            && (int) $device->tenant_id === (int) $user->tenant_id;
    }
}
