<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Screen;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;

class ScreenPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Screen $screen): bool
    {
        return $this->viewAny($user) && $this->sameTenant($screen);
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

    private function sameTenant(Screen $screen): bool
    {
        $tenantId = Tenancy::id();

        return $tenantId !== null && (int) $screen->tenant_id === (int) $tenantId;
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
