<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class StripeWebhookController
{
    public function __construct(private ActivateSubscriptionPlanAction $activate) {}

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('stripe.webhook_secret');
        if ($secret === '' || ! $this->verifySignature($request, $secret)) {
            return response('Invalid signature', 400);
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');

        if ($type === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $tenantId = (int) ($session['metadata']['tenant_id'] ?? $session['client_reference_id'] ?? 0);
            $plan = (string) ($session['metadata']['plan'] ?? '');

            if ($tenantId > 0 && $plan !== '' && array_key_exists($plan, config('billing.plans', []))) {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant !== null) {
                    $this->activate->handle(null, $tenant, $plan, 'stripe_webhook');
                    if (is_string($session['customer'] ?? null) && $session['customer'] !== '') {
                        $tenant->forceFill(['stripe_customer_id' => $session['customer']])->save();
                    }
                }
            }
        }

        return response('ok', 200);
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $header = (string) $request->header('Stripe-Signature', '');
        if ($header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
