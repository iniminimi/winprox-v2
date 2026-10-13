<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Worker;

class CountWorkerAdminFollowUpAction
{
    public function handle(int $tenantId): int
    {
        return Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('needs_admin_follow_up', true)
            ->count();
    }
}
