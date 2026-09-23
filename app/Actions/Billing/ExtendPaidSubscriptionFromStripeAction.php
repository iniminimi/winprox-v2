<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;

/**
 * Verlengt of beëindigt een betaald abonnement na Stripe-webhook (invoice / subscription).
 */
class ExtendPaidSubscriptionFromStripeAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  'invoice_paid'|'subscription_deleted'  $reason
     */
    public function handle(Tenant $tenant, string $reason, ?Carbon $activeUntil = null): Tenant
    {
        if ($reason === 'subscription_deleted') {
            $until = $activeUntil ?? Carbon::now();

            $tenant->forceFill([
                'billing_active_until' => $until,
            ])->save();

            $fresh = $tenant->fresh();

            $this->audit->record(
                userId: null,
                tenantId: (int) $fresh->id,
                action: 'subscription.stripe_ended',
                modelType: Tenant::class,
                modelId: (int) $fresh->id,
                payload: [
                    'id' => $fresh->id,
                    'billing_active_until' => optional($fresh->billing_active_until)->toIso8601String(),
                    'reason' => $reason,
                ],
            );

            return $fresh;
        }

        $periodDays = $tenant->subscriptionPeriodDays();
        $until = $activeUntil ?? Carbon::now()->addDays($periodDays);

        // Alleen verlengen; niet inkorten bij late/duplicate invoice.paid.
        if ($tenant->billing_active_until !== null && $tenant->billing_active_until->gt($until)) {
            return $tenant;
        }

        $tenant->forceFill([
            'billing_active_until' => $until,
            'is_active' => true,
        ])->save();

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: null,
            tenantId: (int) $fresh->id,
            action: 'subscription.stripe_extended',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'billing_active_until' => optional($fresh->billing_active_until)->toIso8601String(),
                'reason' => $reason,
            ],
        );

        return $fresh;
    }
}
