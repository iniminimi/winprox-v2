<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;

class TenantTimeArchivePolicy
{
    public function download(User $user, TenantTimeArchive $archive): bool
    {
        $tenant = Tenant::query()->find($archive->tenant_id);
        if (! $tenant instanceof Tenant || $archive->available_until === null || $archive->available_until->lte(now())) {
            return false;
        }

        return $user->can('manageSubscription', $tenant);
    }
}
