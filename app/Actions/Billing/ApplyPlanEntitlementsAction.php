<?php

namespace App\Actions\Billing;

use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Models\Tenant;

class ApplyPlanEntitlementsAction
{
    public function __construct(private EnsureDefaultClockPointAction $ensureDefaultClockPoint) {}

    public function handle(Tenant $tenant, ?string $planKey = null): Tenant
    {
        if ($tenant->isLegacyWithoutBillingTracking()) {
            return $tenant;
        }

        $config = $this->resolvePlanConfig($tenant, $planKey);
        if ($config === null) {
            return $tenant;
        }

        $wantsTimeModule = (bool) ($config['time_module'] ?? false);

        $tenant->forceFill([
            'has_time_module' => $wantsTimeModule,
            'has_esg_module' => (bool) ($config['esg_module'] ?? false),
        ])->save();

        // Worker-login loopt via Clock Point QR (Team-QR is weg). Zorg altijd voor ≥1 punt.
        if ($wantsTimeModule) {
            $this->ensureDefaultClockPoint->handle(
                $tenant->fresh(),
                __('team.clock_point_qr.default_name'),
                null,
            );
        }

        return $tenant->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePlanConfig(Tenant $tenant, ?string $planKey): ?array
    {
        if ($planKey !== null) {
            $planKey = Tenant::normalizeBillingPlanKey($planKey) ?? $planKey;

            return $planKey === config('billing.trial_plan_facility')
                ? config('billing.trial')
                : config("billing.plans.{$planKey}");
        }

        if ($tenant->isTrialActive()) {
            return config('billing.trial');
        }

        if ($tenant->isPaidSubscriptionActive() || $tenant->isInPaidSubscriptionGrace()) {
            $key = Tenant::normalizeBillingPlanKey($tenant->billing_plan);

            return $key !== null ? config("billing.plans.{$key}") : null;
        }

        return null;
    }
}
