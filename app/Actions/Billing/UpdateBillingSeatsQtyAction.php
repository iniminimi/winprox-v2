<?php

namespace App\Actions\Billing;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Per-seat plannen (Checkmate): tenant kiest het aantal seats zelf
 * (docs/CHECKMATE.md §7). Bij een actieve Stripe-subscription is de
 * subscription-quantity leidend: wij eerst Stripe bijwerken (proratie),
 * dan pas lokaal — zo lopen limiet en facturatie nooit uiteen.
 */
class UpdateBillingSeatsQtyAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, int $qty, ?int $actorUserId = null): Tenant
    {
        if (! $tenant->planUsesSeatQuantity()) {
            throw new InvalidArgumentException('seats_qty_not_editable');
        }

        if ($qty < 1) {
            throw new InvalidArgumentException('seats_qty_invalid');
        }

        $tenant->assertSeatsQtyNotBelowActive($qty);

        $this->syncStripeSubscriptionQuantity($tenant, $qty);

        $tenant->forceFill(['billing_seats_qty' => $qty])->save();
        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.billing_seats_qty_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'billing_seats_qty' => $qty,
                'active_seats' => $fresh->currentSeatsCount(),
            ],
        );

        return $fresh;
    }

    private function syncStripeSubscriptionQuantity(Tenant $tenant, int $qty): void
    {
        $customerId = $tenant->stripe_customer_id;
        if (! config('stripe.enabled') || ! is_string($customerId) || $customerId === '') {
            return;
        }

        $secret = (string) config('stripe.secret');
        $list = Http::withToken($secret)->get('https://api.stripe.com/v1/subscriptions', [
            'customer' => $customerId,
            'status' => 'active',
            'limit' => 10,
        ]);

        if (! $list->successful()) {
            logger()->warning('stripe.seats_qty_sync_failed', [
                'tenant_id' => $tenant->id,
                'stage' => 'list',
                'status' => $list->status(),
                'body' => $list->json() ?? $list->body(),
            ]);

            throw new InvalidArgumentException('seats_qty_stripe_failed');
        }

        $subscriptions = collect($list->json('data') ?? []);
        $subscription = $subscriptions->first(
            fn ($sub) => (int) data_get($sub, 'metadata.tenant_id') === (int) $tenant->id,
        ) ?? $subscriptions->first();

        // Geen actieve Stripe-subscription (bv. manueel geactiveerd): niets te syncen.
        if (! is_array($subscription)) {
            return;
        }

        $subscriptionId = $subscription['id'] ?? null;
        $itemId = data_get($subscription, 'items.data.0.id');
        if (! is_string($subscriptionId) || ! is_string($itemId)) {
            return;
        }

        $update = Http::withToken($secret)->asForm()
            ->post("https://api.stripe.com/v1/subscriptions/{$subscriptionId}", [
                'items[0][id]' => $itemId,
                'items[0][quantity]' => $qty,
                'proration_behavior' => 'create_prorations',
            ]);

        if (! $update->successful()) {
            logger()->warning('stripe.seats_qty_sync_failed', [
                'tenant_id' => $tenant->id,
                'stage' => 'update',
                'subscription_id' => $subscriptionId,
                'status' => $update->status(),
                'body' => $update->json() ?? $update->body(),
            ]);

            throw new InvalidArgumentException('seats_qty_stripe_failed');
        }
    }
}
