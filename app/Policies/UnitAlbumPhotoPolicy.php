<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\UnitAlbumPhoto;
use App\Models\User;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;

class UnitAlbumPhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->albumAvailableFor($user);
    }

    public function view(User $user, UnitAlbumPhoto $photo): bool
    {
        return $this->viewAny($user)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $photo->tenant_id);
    }

    public function send(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, UnitAlbumPhoto $photo): bool
    {
        return $this->view($user, $photo);
    }

    private function albumAvailableFor(User $user): bool
    {
        $tenant = $user->tenant_id !== null
            ? $user->tenant
            : ($user->is_superuser && Tenancy::id() !== null ? Tenant::query()->find(Tenancy::id()) : null);

        return $tenant !== null && ! CheckmateMode::isActive($tenant);
    }
}
