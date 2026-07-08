<?php

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class ToggleEsgModuleAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->has_esg_module;

        $tenant->update(['has_esg_module' => $newValue]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.esg_module_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['has_esg_module' => $newValue],
        );
    }
}
