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

        $tenant->update(['has_time_module' => $newValue]);

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
            payload: ['has_time_module' => $newValue],
        );
    }
}
