<?php

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class SetBillingUnitsCapAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, int $unitsCap, ?int $actorUserId = null): Tenant
    {
        if (Tenant::normalizeBillingPlanKey($tenant->billing_plan) !== 'corporate') {
            throw new \InvalidArgumentException('not_corporate');
        }

        $tenant->update(['billing_units_cap' => $unitsCap]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.billing_units_cap_updated',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['billing_units_cap' => $unitsCap],
        );

        return $tenant->fresh();
    }
}
