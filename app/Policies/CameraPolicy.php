<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Camera;
use App\Models\CameraPhoto;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;

class CameraPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Camera $camera): bool
    {
        return $this->viewAny($user) && $this->sameTenant($camera->tenant_id);
    }

    public function view(User $user, CameraPhoto $photo): bool
    {
        return $this->viewAny($user) && $this->sameTenant($photo->tenant_id);
    }

    private function allowed(User $user): bool
    {
        $tenant = $this->tenant($user);
        if ($tenant === null || CheckmateMode::isActive($tenant)) {
            return false;
        }

        if ($user->is_superuser && Tenancy::id() !== null) {
            return true;
        }

        return $user->isAdmin();
    }

    private function sameTenant(int $tenantId): bool
    {
        $current = Tenancy::id();

        return $current !== null && (int) $current === $tenantId;
    }

    private function tenant(User $user): ?Tenant
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
