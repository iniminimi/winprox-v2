<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ExtendPaidSubscriptionFromStripeAction;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class StripeWebhookController
{
    public function __construct(
        private ActivateSubscriptionPlanAction $activate,
        private ExtendPaidSubscriptionFromStripeAction $extend,
    ) {}

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('stripe.webhook_secret');
        if ($secret === '' || ! $this->verifySignature($request, $secret)) {
            return response('Invalid signature', 400);
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');
        $object = $payload['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted(is_array($object) ? $object : []);
        }

        if ($type === 'invoice.paid') {
            $this->handleInvoicePaid(is_array($object) ? $object : []);
        }

        if ($type === 'customer.subscription.deleted') {
            $this->handleSubscriptionDeleted(is_array($object) ? $object : []);
        }

        return response('ok', 200);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutCompleted(array $session): void
    {
        $tenantId = (int) ($session['metadata']['tenant_id'] ?? $session['client_reference_id'] ?? 0);
        $plan = (string) ($session['metadata']['plan'] ?? '');

        if ($tenantId <= 0 || $plan === '' || ! array_key_exists($plan, config('billing.plans', []))) {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }

        $this->activate->handle(null, $tenant, $plan, 'stripe_webhook');

        if (is_string($session['customer'] ?? null) && $session['customer'] !== '') {
            $tenant->forceFill(['stripe_customer_id' => $session['customer']])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function handleInvoicePaid(array $invoice): void
    {
        // Eerste factuur bij checkout: activate loopt al via checkout.session.completed.
        $billingReason = (string) ($invoice['billing_reason'] ?? '');
        if ($billingReason === 'subscription_create') {
            return;
        }

        $tenant = $this->resolveTenantFromStripeObject($invoice);
        if ($tenant === null) {
            return;
        }

        $periodEnd = $this->periodEndFromInvoice($invoice);
        $this->extend->handle($tenant, 'invoice_paid', $periodEnd);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function handleSubscriptionDeleted(array $subscription): void
    {
        $tenant = $this->resolveTenantFromStripeObject($subscription);
        if ($tenant === null) {
            return;
        }

        $endedAt = isset($subscription['ended_at']) && is_numeric($subscription['ended_at'])
            ? Carbon::createFromTimestamp((int) $subscription['ended_at'])
            : Carbon::now();

        $this->extend->handle($tenant, 'subscription_deleted', $endedAt);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function resolveTenantFromStripeObject(array $object): ?Tenant
    {
        $tenantId = (int) ($object['metadata']['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $byMeta = Tenant::query()->find($tenantId);
            if ($byMeta !== null) {
                return $byMeta;
            }
        }

        $customerId = $object['customer'] ?? null;
        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return Tenant::query()->where('stripe_customer_id', $customerId)->first();
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function periodEndFromInvoice(array $invoice): ?Carbon
    {
        $lines = $invoice['lines']['data'] ?? null;
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $end = $line['period']['end'] ?? null;
            if (is_numeric($end)) {
                return Carbon::createFromTimestamp((int) $end);
            }
        }

        return null;
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
