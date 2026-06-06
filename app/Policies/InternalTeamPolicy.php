<?php

namespace App\Policies;

use App\Models\InternalTeam;
use App\Models\User;

/**
 * RBAC voor teams (V2-spec §6.0/§6.2):
 * - aanmaken/deactiveren = beheerder (admin)
 * - inhoud bewerken + workers beheren = beheerder OF medewerker (employee)
 *
 * Acties van buiten de tenant zijn altijd geweigerd.
 */
class InternalTeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InternalTeam $team): bool
    {
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }

    public function deactivate(User $user, InternalTeam $team): bool
    {
        return $user->tenant_id === $team->tenant_id && $user->isAdmin();
    }

    /** Inhoud + workers beheren (admin of medewerker). */
    public function manageContent(User $user): bool
    {
        return $user->is_superuser || ($user->tenant_id !== null && ($user->isAdmin() || $user->isEmployee()));
    }

    public function syncCategories(User $user, InternalTeam $team): bool
    {
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isEmployee();
    }
}
