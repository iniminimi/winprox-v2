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

        // Checkout Studio (fixed_by_ui) + bestaande WinProx sample_only (mode/urls/line_items).
        // metadata / client_reference_id / customer blijven voor fulfillment (webhook + plan-activatie).
        $payload = [
            'ui_mode' => 'hosted_page',
            'mode' => 'subscription',
            'billing_address_collection' => 'auto',
            'phone_number_collection[enabled]' => 'true',
            'automatic_tax[enabled]' => 'false',
            'allow_promotion_codes' => 'false',
            'payment_method_collection' => 'always',
            'submit_type' => 'auto',
            'name_collection[individual][enabled]' => 'true',
            'name_collection[individual][optional]' => 'true',
            'name_collection[business][enabled]' => 'true',
            'name_collection[business][optional]' => 'true',
            'integration_identifier' => 'hosted_web_0001',
            'origin_context' => 'web',
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
            return null;
        }

        return $response->json('url');
    }
}
