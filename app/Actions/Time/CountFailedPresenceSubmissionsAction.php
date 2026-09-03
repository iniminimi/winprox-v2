<?php

namespace App\Actions\Time;

use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use App\Models\Tenant;

class CountFailedPresenceSubmissionsAction
{
    public function handle(int $tenantId): int
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || ! $tenant->presenceComplianceEnabled()) {
            return 0;
        }

        return PresenceSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PresenceSubmissionStatus::Failed)
            ->count();
    }
}
