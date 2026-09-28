<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * Haalt checkout.session op en activeert het plan voor de tenant (Stripe-webhook / success redirect).
 */
class FulfillStripeCheckoutSessionAction
{
    public function __construct(private ActivateSubscriptionPlanAction $activate) {}

    public function handle(string $sessionId): bool
    {
        if (! config('stripe.enabled')) {
            return false;
        }

        $secret = (string) config('stripe.secret');
        $response = Http::withToken($secret)
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}", [
                'expand[]' => 'line_items',
            ]);

        if (! $response->successful()) {
            return false;
        }

        $status = (string) $response->json('payment_status', '');
        if ($status !== 'paid' && $status !== 'no_payment_required') {
            return false;
        }

        $tenantId = (int) ($response->json('metadata.tenant_id') ?? $response->json('client_reference_id') ?? 0);
        $plan = (string) ($response->json('metadata.plan') ?? '');

        if ($tenantId <= 0 || $plan === '' || ! array_key_exists($plan, config('billing.plans', []))) {
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return false;
        }

        // Per-seat plannen (Checkmate): het effectief betaalde aantal licenties
        // uit de Checkout-line-items is de seat-limiet.
        $seatsQty = null;
        if ((bool) config("billing.plans.{$plan}.seats_qty_editable", false)) {
            $qty = $response->json('line_items.data.0.quantity');
            $seatsQty = is_numeric($qty) ? max(1, (int) $qty) : null;
        }

        $this->activate->handle(null, $tenant, $plan, 'stripe', seatsQty: $seatsQty);

        $customerId = $response->json('customer');
        if (is_string($customerId) && $customerId !== '') {
            $tenant->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        return true;
    }
}
