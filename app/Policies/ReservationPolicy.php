<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Support\Tenant\TenantWorkMenuAccess;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->is_superuser || $user->tenant_id !== null)
            && $this->workMenuReservationsEnabledFor($user);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->sameTenant($user, $reservation);
    }

    public function create(User $user): bool
    {
        return ($user->isAdmin() || $user->isEmployee())
            && $this->workMenuReservationsEnabledFor($user);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $this->sameTenant($user, $reservation)
            && ($user->isAdmin() || $user->isEmployee());
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    private function sameTenant(User $user, Reservation $reservation): bool
    {
        if ($user->is_superuser) {
            return true;
        }

        return $user->tenant_id !== null && (int) $user->tenant_id === (int) $reservation->tenant_id;
    }

    private function workMenuReservationsEnabledFor(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return TenantWorkMenuAccess::reservationsEnabled($user->tenant);
        }

        if ($user->is_superuser) {
            return TenantWorkMenuAccess::activeTenantReservationsEnabled();
        }

        return false;
    }
}
