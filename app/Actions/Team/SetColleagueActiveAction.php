<?php

namespace App\Actions\Team;

use App\Models\User;

/**
 * (De)activeert een collega-gebruiker. Inactieve gebruikers kunnen niet inloggen
 * (afgedwongen in de login-flow).
 */
class SetColleagueActiveAction
{
    public function handle(User $user, bool $active): User
    {
        $user->update(['is_active' => $active]);

        return $user;
    }
}
