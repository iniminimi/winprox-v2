<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpsPortal;
use App\Models\User;

class OpsPortalPolicy
{
    public function view(User $user, OpsPortal $portal): bool
    {
        return $user->is_superuser && $portal->is_active;
    }

    public function use(User $user, OpsPortal $portal): bool
    {
        return $this->view($user, $portal);
    }
}
