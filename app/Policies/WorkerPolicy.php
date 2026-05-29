<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superuser || $user->tenant_id !== null;
    }

    public function view(User $user, Worker $worker): bool
    {
        return $user->is_superuser || (int) $user->tenant_id === (int) $worker->tenant_id;
    }
}
