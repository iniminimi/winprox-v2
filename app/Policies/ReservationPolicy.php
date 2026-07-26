<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->sameTenant($user, $reservation);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
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
}
