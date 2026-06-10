<?php

namespace App\Policies;

use App\Models\EmailUnsubscribe;
use App\Models\User;

class EmailUnsubscribePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser;
    }

    public function delete(User $user, EmailUnsubscribe $emailUnsubscribe): bool
    {
        return $user->is_superuser;
    }
}
