<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromoCampaign;
use App\Models\User;

class PromoCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser;
    }

    public function view(User $user, PromoCampaign $campaign): bool
    {
        return $user->is_superuser;
    }

    public function create(User $user): bool
    {
        return $user->is_superuser;
    }

    public function update(User $user, PromoCampaign $campaign): bool
    {
        return $user->is_superuser;
    }

    public function delete(User $user, PromoCampaign $campaign): bool
    {
        return $user->is_superuser;
    }
}
