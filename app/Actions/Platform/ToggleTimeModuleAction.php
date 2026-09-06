<?php

namespace App\Actions\Platform;

use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class ToggleTimeModuleAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureDefaultClockPointAction $ensureDefaultClockPoint,
    ) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->has_time_module;

        $updates = ['has_time_module' => $newValue];
        if (! $newValue) {
            $updates['presence_compliance_enabled'] = false;
        }

        $tenant->update($updates);

        if ($newValue) {
            $this->ensureDefaultClockPoint->handle(
                $tenant,
                __('team.clock_point_qr.default_name'),
                $actorUserId,
            );
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.time_module_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'has_time_module' => $newValue,
                'presence_compliance_enabled' => $newValue ? (bool) $tenant->fresh()->presence_compliance_enabled : false,
            ],
        );
    }
}
