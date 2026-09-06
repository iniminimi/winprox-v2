<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Enums\PresenceComplianceScope;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class TogglePresenceComplianceAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->presence_compliance_enabled;

        if ($newValue && ! TimeModuleAccess::tenantHasModule($tenant)) {
            throw new InvalidArgumentException('time_module_disabled');
        }

        $updates = ['presence_compliance_enabled' => $newValue];
        if ($newValue && $tenant->presence_compliance_scope === null) {
            $updates['presence_compliance_scope'] = PresenceComplianceScope::CiaoCleaning->value;
        }

        $tenant->update($updates);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.presence_compliance_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'presence_compliance_enabled' => $newValue,
                'presence_compliance_scope' => $tenant->fresh()->presence_compliance_scope,
            ],
        );
    }
}
