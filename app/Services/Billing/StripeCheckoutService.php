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
     * @return array{url: ?string, error: ?string}
     */
    public function createCheckoutSession(User $actor, Tenant $tenant, string $plan): array
    {
        if (! config('stripe.offer_checkout')) {
            return ['url' => null, 'error' => 'offer_checkout_off'];
        }

        if (! config('stripe.enabled')) {
            return ['url' => null, 'error' => 'missing_secret'];
        }

        $priceId = config("stripe.price_ids.{$plan}");
        if (! is_string($priceId) || $priceId === '') {
            return ['url' => null, 'error' => "missing_price:{$plan}"];
        }

        $secret = (string) config('stripe.secret');
        $successUrl = url(config('stripe.success_path', '/subscription')).'?stripe=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = url(config('stripe.cancel_path', '/subscription')).'?stripe=cancel';

        // Minimale subscription-checkout (hosted is default). Studio-extras weggelaten:
        // onbekende params / one-time prices breken de sessie stil.
        $payload = [
            'mode' => 'subscription',
            'billing_address_collection' => 'auto',
            'payment_method_collection' => 'always',
            'line_items[0][price]' => $priceId,
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
            $body = $response->json();
            $stripeMessage = is_array($body)
                ? (string) data_get($body, 'error.message', $response->body())
                : (string) $response->body();

            logger()->warning('stripe.checkout_session_failed', [
                'plan' => $plan,
                'price_id' => $priceId,
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'body' => $body ?? $response->body(),
            ]);

            return ['url' => null, 'error' => $stripeMessage !== '' ? $stripeMessage : 'stripe_http_'.$response->status()];
        }

        $url = $response->json('url');

        if (! is_string($url) || $url === '') {
            return ['url' => null, 'error' => 'missing_checkout_url'];
        }

        return ['url' => $url, 'error' => null];
    }
}
