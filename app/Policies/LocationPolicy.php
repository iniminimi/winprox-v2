<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Tenancy;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Location $location): bool
    {
        return SuperuserTenantAccess::canAccessTenant($user, (int) $location->tenant_id)
            && $user->canAccessLocation((int) $location->id);
    }

    public function create(User $user): bool
    {
        return $this->mayManage($user, null);
    }

    /** Locatiegegevens wijzigen. Bezetting en unit-import blijven via update. */
    public function manage(User $user, Location $location): bool
    {
        return $this->mayManage($user, $location);
    }

    public function update(User $user, Location $location): bool
    {
        return $this->view($user, $location);
    }

    public function deactivate(User $user, Location $location): bool
    {
        return $this->manage($user, $location);
    }

    public function activate(User $user, Location $location): bool
    {
        return $this->manage($user, $location);
    }

    /** Beginscherm: een toestel koppelen. Een medewerker alleen op haar locatie. */
    public function pairDeviceAny(User $user): bool
    {
        return $this->mayPairDevice($user);
    }

    public function pairDevice(User $user, Location $location): bool
    {
        return $this->mayPairDevice($user) && $this->view($user, $location);
    }

    private function mayManage(User $user, ?Location $location): bool
    {
        if ($location !== null && ! $this->view($user, $location)) {
            return false;
        }

        if ($user->is_superuser) {
            return true;
        }

        if ($user->isAdmin() && $user->tenant_id !== null) {
            return true;
        }

        if (! $user->isEmployee() || $user->tenant_id === null) {
            return false;
        }

        $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();

        return $tenant instanceof Tenant && $tenant->employeesMayManageLocations();
    }

    private function mayPairDevice(User $user): bool
    {
        $tenantId = Tenancy::id() ?? $user->tenant_id;
        if ($tenantId === null) {
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant || CheckmateMode::isActive($tenant)) {
            return false;
        }

        if ($user->is_superuser && Tenancy::id() !== null) {
            return true;
        }

        return $user->isAdmin() || $user->isEmployee();
    }
}
