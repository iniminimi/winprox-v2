<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class StripeCheckoutService
{
    public function isConfiguredForPlan(string $plan): bool
    {
        if (! config('stripe.enabled')) {
            return false;
        }

        $priceId = config("stripe.price_ids.{$plan}");

        return is_string($priceId) && $priceId !== '';
    }

    /**
     * @return null|string Checkout-URL of null wanneer Stripe niet geconfigureerd is.
     */
    public function createCheckoutSession(User $actor, Tenant $tenant, string $plan): ?string
    {
        if (! $this->isConfiguredForPlan($plan)) {
            return null;
        }

        $secret = (string) config('stripe.secret');
        $successUrl = url(config('stripe.success_path', '/subscription')).'?stripe=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = url(config('stripe.cancel_path', '/subscription')).'?stripe=cancel';

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'line_items[0][price]' => config("stripe.price_ids.{$plan}"),
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $tenant->id,
                'metadata[tenant_id]' => (string) $tenant->id,
                'metadata[plan]' => $plan,
                'customer_email' => $actor->email,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('url');
    }
}
