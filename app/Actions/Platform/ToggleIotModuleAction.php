<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class ToggleIotModuleAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->has_iot_module;

        $tenant->update(['has_iot_module' => $newValue]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.iot_module_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['has_iot_module' => $newValue],
        );
    }
}
