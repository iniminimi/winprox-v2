<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Enums\PresenceComplianceScope;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;

class TogglePresenceComplianceAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureDefaultClockPointAction $ensureDefaultClockPoint,
    ) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->presence_compliance_enabled;

        $updates = ['presence_compliance_enabled' => $newValue];
        $enabledTime = false;

        // CIAO = Time-add-on: bij aanzetten Time mee aanzetten (geen Corporate-eis).
        if ($newValue && ! TimeModuleAccess::tenantHasModule($tenant)) {
            $updates['has_time_module'] = true;
            $enabledTime = true;
        }

        if ($newValue && $tenant->presence_compliance_scope === null) {
            $updates['presence_compliance_scope'] = PresenceComplianceScope::CiaoCleaning->value;
        }

        $tenant->update($updates);

        if ($enabledTime) {
            $this->ensureDefaultClockPoint->handle(
                $tenant->fresh(),
                __('team.clock_point_qr.default_name'),
                $actorUserId,
            );
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.presence_compliance_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'presence_compliance_enabled' => $newValue,
                'presence_compliance_scope' => $tenant->fresh()->presence_compliance_scope,
                'has_time_module' => (bool) $tenant->fresh()->has_time_module,
            ],
        );
    }
}
