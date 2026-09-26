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
        $checkmateMode = (bool) ($config['checkmate_mode'] ?? false);

        $updates = [
            'has_time_module' => $wantsTimeModule,
            'has_esg_module' => (bool) ($config['esg_module'] ?? false),
            'has_iot_module' => (bool) ($config['iot_module'] ?? false),
            'checkmate_mode' => $checkmateMode,
        ];

        // Checkmate-preset (docs/CHECKMATE.md §1): GPS-werkbezoeken verplicht aan.
        if ($checkmateMode) {
            $updates['has_time_module'] = true;
            $updates['time_gps_visits'] = true;
            $updates['time_gps_visit_radius_meters'] = (int) ($config['gps_visit_radius_meters'] ?? 100);
        }

        $tenant->forceFill($updates)->save();

        // Worker-aanmelden loopt via Clock Point QR, ook zonder Time (prikklok).
        // Checkmate heeft geen Facility maar wél een Clock Point (/cp/{token}).
        if ((bool) ($config['includes_facility'] ?? false) || $checkmateMode) {
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
