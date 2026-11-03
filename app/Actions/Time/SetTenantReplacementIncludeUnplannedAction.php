<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;

class SetTenantReplacementIncludeUnplannedAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $include, ?int $actorUserId): void
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $tenant->update(['time_replacement_include_unplanned' => $include]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.replacement_include_unplanned',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['include_unplanned' => $include],
        );
    }
}
