<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class QrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $qrCode->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function link(User $user, QrCode $qrCode): bool
    {
        // Only unassigned QR codes can be linked
        if (!$qrCode->canBeLinked()) {
            return false;
        }

        return $this->view($user, $qrCode);
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        return $this->view($user, $qrCode);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $this->view($user, $qrCode);
    }
}
