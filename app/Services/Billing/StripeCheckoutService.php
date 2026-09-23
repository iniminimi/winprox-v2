<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class StripeCheckoutService
{
    public function isConfiguredForPlan(string $plan): bool
    {
        if (! config('stripe.offer_checkout')) {
            return false;
        }

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

        // Hosted Checkout (subscription). Geen submit_type: die geldt alleen bij mode=payment.
        // metadata / client_reference_id / customer: nodig voor WinProx-fulfillment.
        $payload = [
            'ui_mode' => 'hosted',
            'mode' => 'subscription',
            'billing_address_collection' => 'auto',
            'phone_number_collection[enabled]' => 'true',
            'automatic_tax[enabled]' => 'false',
            'allow_promotion_codes' => 'false',
            'payment_method_collection' => 'always',
            'name_collection[individual][enabled]' => 'true',
            'name_collection[individual][optional]' => 'true',
            'name_collection[business][enabled]' => 'true',
            'name_collection[business][optional]' => 'true',
            'line_items[0][price]' => config("stripe.price_ids.{$plan}"),
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $tenant->id,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[plan]' => $plan,
            'subscription_data[metadata][tenant_id]' => (string) $tenant->id,
            'subscription_data[metadata][plan]' => $plan,
            'customer_email' => $actor->email,
        ];

        if (is_string($tenant->stripe_customer_id) && $tenant->stripe_customer_id !== '') {
            $payload['customer'] = $tenant->stripe_customer_id;
            unset($payload['customer_email']);
        }

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if (! $response->successful()) {
            logger()->warning('stripe.checkout_session_failed', [
                'plan' => $plan,
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return null;
        }

        $url = $response->json('url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
