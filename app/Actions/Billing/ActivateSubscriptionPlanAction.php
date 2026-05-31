<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;

class ActivateSubscriptionPlanAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(?User $actor, Tenant $tenant, string $plan, string $source = 'manual'): Tenant
    {
        $periodDays = Tenant::subscriptionPeriodDaysForPlan($plan);

        $tenant->forceFill([
            'billing_plan' => $plan,
            'billing_active_until' => Carbon::now()->addDays($periodDays),
            'trial_ends_at' => now(),
            'is_active' => true,
        ])->save();

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actor?->id,
            tenantId: (int) $fresh->id,
            action: 'subscription.plan_activated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'plan' => $plan,
                'source' => $source,
                'billing_active_until' => optional($fresh->billing_active_until)->toIso8601String(),
            ],
        );

        return $fresh;
    }
}
