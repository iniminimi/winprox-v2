<?php

namespace App\Actions\Team;

use App\Models\User;

class UpdateColleagueAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? $user->role,
        ]);

        return $user;
    }
}
