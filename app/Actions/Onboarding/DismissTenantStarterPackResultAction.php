<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Tenant;
use App\Models\User;

class DismissTenantStarterPackResultAction
{
    public function handle(Tenant $tenant, User $actor): void
    {
        unset($actor);

        if (! $tenant->hasStarterPack()) {
            return;
        }

        if ($tenant->starter_pack_result_dismissed_at !== null) {
            return;
        }

        $tenant->forceFill([
            'starter_pack_result_dismissed_at' => now(),
        ])->save();
    }
}
