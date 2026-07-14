<?php

namespace App\Actions\Billing;

use App\Models\Tenant;

class ApplyPlanEntitlementsAction
{
    public function handle(Tenant $tenant, ?string $planKey = null): Tenant
    {
        if ($tenant->isLegacyWithoutBillingTracking()) {
            return $tenant;
        }

        $config = $this->resolvePlanConfig($tenant, $planKey);
        if ($config === null) {
            return $tenant;
        }

        $tenant->forceFill([
            'has_time_module' => (bool) ($config['time_module'] ?? false),
            'has_esg_module' => (bool) ($config['esg_module'] ?? false),
        ])->save();

        return $tenant->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePlanConfig(Tenant $tenant, ?string $planKey): ?array
    {
        if ($planKey !== null) {
            return $planKey === config('billing.trial_plan_facility')
                ? config('billing.trial')
                : config("billing.plans.{$planKey}");
        }

        if ($tenant->isTrialActive()) {
            return config('billing.trial');
        }

        if ($tenant->isPaidSubscriptionActive() || $tenant->isInPaidSubscriptionGrace()) {
            $key = $tenant->billing_plan;

            return is_string($key) ? config("billing.plans.{$key}") : null;
        }

        return null;
    }
}
