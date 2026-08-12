<?php

namespace App\Actions\Platform;

use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;

class AssignCorporateSubscriptionAction
{
    public function __construct(
        private AuditRecorder $audit,
        private ApplyPlanEntitlementsAction $applyEntitlements,
    ) {}

    public function handle(Tenant $tenant, int $unitsCap, User $actor): Tenant
    {
        $periodDays = Tenant::subscriptionPeriodDaysForPlan('corporate');

        $tenant->forceFill([
            'billing_plan' => 'corporate',
            'billing_units_cap' => $unitsCap,
            'billing_active_until' => Carbon::now()->addDays($periodDays),
            'trial_ends_at' => now(),
            'is_active' => true,
        ])->save();

        $fresh = $this->applyEntitlements->handle($tenant->fresh(), 'corporate');

        $this->audit->record(
            userId: $actor->id,
            tenantId: (int) $fresh->id,
            action: 'subscription.corporate_assigned',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'plan' => 'corporate',
                'billing_units_cap' => $unitsCap,
                'billing_active_until' => optional($fresh->billing_active_until)->toIso8601String(),
            ],
        );

        return $fresh;
    }
}
