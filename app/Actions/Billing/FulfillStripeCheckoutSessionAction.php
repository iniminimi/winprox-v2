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
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

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

        $this->activate->handle(null, $tenant, $plan, 'stripe');

        $customerId = $response->json('customer');
        if (is_string($customerId) && $customerId !== '') {
            $tenant->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        return true;
    }
}
