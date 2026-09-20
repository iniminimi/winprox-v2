<?php

namespace App\Actions\Billing;

use App\Actions\TenantPurge\CancelOpenExpiredTrialPurgesForTenantAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ActivateSubscriptionPlanAction
{
    public function __construct(
        private AuditRecorder $audit,
        private ApplyPlanEntitlementsAction $applyEntitlements,
        private CancelOpenExpiredTrialPurgesForTenantAction $cancelExpiredTrialPurges,
    ) {}

    /**
     * @param  'manual'|'stripe'|'platform'  $source
     */
    public function handle(
        ?User $actor,
        Tenant $tenant,
        string $plan,
        string $source = 'manual',
        ?int $unitsCap = null,
    ): Tenant {
        $plan = Tenant::normalizeBillingPlanKey($plan) ?? $plan;

        if (! is_array(config("billing.plans.{$plan}"))) {
            throw new InvalidArgumentException('unknown_plan');
        }

        $bypassSelfActivate = in_array($source, ['stripe', 'platform'], true);
        if (! $bypassSelfActivate && ! (bool) config("billing.plans.{$plan}.self_activate", true)) {
            throw new InvalidArgumentException('plan_not_self_activate');
        }

        if ($plan === 'corporate' && $unitsCap === null && $tenant->billing_units_cap === null) {
            throw new InvalidArgumentException('corporate_units_cap_required');
        }

        $periodDays = Tenant::subscriptionPeriodDaysForPlan($plan);

        $tenant->forceFill([
            'billing_plan' => $plan,
            'billing_active_until' => Carbon::now()->addDays($periodDays),
            'trial_ends_at' => now(),
            'is_active' => true,
            'billing_units_cap' => $plan === 'corporate'
                ? ($unitsCap ?? $tenant->billing_units_cap)
                : null,
        ])->save();

        $fresh = $this->applyEntitlements->handle($tenant->fresh(), $plan);

        $this->cancelExpiredTrialPurges->handle($fresh, $actor);

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
                'billing_units_cap' => $fresh->billing_units_cap,
                'billing_active_until' => optional($fresh->billing_active_until)->toIso8601String(),
            ],
        );

        return $fresh;
    }
}
