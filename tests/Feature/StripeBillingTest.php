<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ExtendPaidSubscriptionFromStripeAction;
use App\Actions\Billing\FulfillStripeCheckoutSessionAction;
use App\Actions\Billing\UpdateBillingSeatsQtyAction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulated_activation_when_stripe_not_configured(): void
    {
        config(['stripe.enabled' => false]);

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
        $admin = User::factory()->admin()->for($tenant)->create();

        $service = app(StripeCheckoutService::class);
        $this->assertFalse($service->isConfiguredForPlan('winprox_50'));

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_50', 'platform');

        $tenant->refresh();
        $this->assertSame('winprox_50', $tenant->billing_plan);
        $this->assertTrue($tenant->isPaidSubscriptionActive());
        $this->assertTrue($tenant->hasTimeModule());
        $this->assertTrue($tenant->billing_active_until->lte(now()->addDays(30)));
        $this->assertTrue($tenant->billing_active_until->gte(now()->addDays(29)));
    }

    public function test_stripe_checkout_stays_off_when_offer_checkout_is_false(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.offer_checkout' => false,
            'stripe.price_ids.winprox_10' => 'price_test',
        ]);

        $service = app(StripeCheckoutService::class);
        $this->assertFalse($service->isConfiguredForPlan('winprox_10'));
    }

    public function test_realigns_facility_subscription_after_yearly_misactivation(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now(),
            'billing_plan' => 'facility_100',
            'billing_active_until' => now()->addDays(364),
        ]);

        app(\App\Actions\Billing\RealignSubscriptionPeriodAction::class)->handle($tenant);

        $tenant->refresh();
        $this->assertTrue($tenant->billing_active_until->lte(now()->addDays(30)));
        $this->assertTrue($tenant->billing_active_until->gte(now()->addDays(29)));
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        config(['stripe.webhook_secret' => 'whsec_test']);

        $this->postJson(route('stripe.webhook'), ['type' => 'checkout.session.completed'])
            ->assertStatus(400);
    }

    public function test_extends_subscription_on_invoice_paid(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'winprox_10',
            'billing_active_until' => now()->addDays(2),
            'stripe_customer_id' => 'cus_test_extend',
        ]);

        $until = Carbon::now()->addDays(35)->startOfSecond();
        app(ExtendPaidSubscriptionFromStripeAction::class)->handle($tenant, 'invoice_paid', $until);

        $tenant->refresh();
        $this->assertSame($until->toDateTimeString(), $tenant->billing_active_until->toDateTimeString());
        $this->assertTrue($tenant->is_active);
    }

    public function test_ends_subscription_on_stripe_deleted(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'winprox_10',
            'billing_active_until' => now()->addDays(20),
            'stripe_customer_id' => 'cus_test_end',
        ]);

        $ended = Carbon::now()->startOfSecond();
        app(ExtendPaidSubscriptionFromStripeAction::class)->handle($tenant, 'subscription_deleted', $ended);

        $tenant->refresh();
        $this->assertSame($ended->toDateTimeString(), $tenant->billing_active_until->toDateTimeString());
    }

    public function test_retries_checkout_without_stale_test_customer_id(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.offer_checkout' => true,
            'stripe.secret' => 'sk_live_test',
            'stripe.price_ids.winprox_5' => 'price_live_5',
        ]);

        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->addDays(5),
            'stripe_customer_id' => 'cus_VJSdQU6JXBFF7u',
        ]);
        $admin = User::factory()->admin()->for($tenant)->create(['email' => 'admin@example.com']);

        \Illuminate\Support\Facades\Http::fake([
            'api.stripe.com/v1/checkout/sessions' => \Illuminate\Support\Facades\Http::sequence()
                ->push([
                    'error' => [
                        'message' => "No such customer: 'cus_VJSdQU6JXBFF7u'",
                        'type' => 'invalid_request_error',
                    ],
                ], 400)
                ->push([
                    'id' => 'cs_test',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test',
                ], 200),
        ]);

        $result = app(StripeCheckoutService::class)->createCheckoutSession($admin, $tenant, 'winprox_5');

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test', $result['url']);
        $this->assertNull($result['error']);
        $this->assertNull($tenant->fresh()->stripe_customer_id);

        Http::assertSentCount(2);
    }

    public function test_checkmate_checkout_sends_seat_quantity_and_adjustable(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.offer_checkout' => true,
            'stripe.secret' => 'sk_test',
            'stripe.price_ids.checkmate' => 'price_checkmate',
        ]);

        $tenant = Tenant::factory()->create(['billing_seats_qty' => 7]);
        $admin = User::factory()->admin()->for($tenant)->create();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_cm',
                'url' => 'https://checkout.stripe.com/c/pay/cs_cm',
            ], 200),
        ]);

        $service = app(StripeCheckoutService::class);
        $this->assertTrue($service->isConfiguredForPlan('checkmate'));

        $result = $service->createCheckoutSession($admin, $tenant, 'checkmate');

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_cm', $result['url']);
        $this->assertNull($result['error']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/v1/checkout/sessions')
                && $data['line_items[0][price]'] === 'price_checkmate'
                && (int) $data['line_items[0][quantity]'] === 7
                && ($data['line_items[0][adjustable_quantity][enabled]'] ?? null) === 'true'
                && (int) ($data['line_items[0][adjustable_quantity][minimum]'] ?? 0) === 1;
        });
    }

    public function test_checkout_sends_automatic_tax_params_when_enabled(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.offer_checkout' => true,
            'stripe.secret' => 'sk_test',
            'stripe.automatic_tax' => true,
            'stripe.price_ids.winprox_5' => 'price_5',
        ]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->for($tenant)->create();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_tax',
                'url' => 'https://checkout.stripe.com/c/pay/cs_tax',
            ], 200),
        ]);

        $result = app(StripeCheckoutService::class)->createCheckoutSession($admin, $tenant, 'winprox_5');

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_tax', $result['url']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['automatic_tax[enabled]'] ?? null) === 'true'
                && ($data['tax_id_collection[enabled]'] ?? null) === 'true'
                && ($data['billing_address_collection'] ?? null) === 'required'
                && ($data['customer_update[address]'] ?? null) === 'auto';
        });
    }

    public function test_checkout_omits_automatic_tax_params_by_default(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.offer_checkout' => true,
            'stripe.secret' => 'sk_test',
            'stripe.automatic_tax' => false,
            'stripe.price_ids.winprox_5' => 'price_5',
        ]);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->for($tenant)->create();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_notax',
                'url' => 'https://checkout.stripe.com/c/pay/cs_notax',
            ], 200),
        ]);

        app(StripeCheckoutService::class)->createCheckoutSession($admin, $tenant, 'winprox_5');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ! array_key_exists('automatic_tax[enabled]', $data)
                && ($data['billing_address_collection'] ?? null) === 'auto';
        });
    }

    public function test_checkmate_fulfill_sets_billing_seats_qty_from_line_items(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test',
        ]);

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response([
                'id' => 'cs_paid',
                'payment_status' => 'paid',
                'customer' => 'cus_paid',
                'client_reference_id' => (string) $tenant->id,
                'metadata' => ['tenant_id' => $tenant->id, 'plan' => 'checkmate'],
                'line_items' => ['data' => [['quantity' => 12]]],
            ], 200),
        ]);

        $this->assertTrue(app(FulfillStripeCheckoutSessionAction::class)->handle('cs_paid'));

        $tenant->refresh();
        $this->assertSame('checkmate', $tenant->billing_plan);
        $this->assertSame(12, $tenant->billing_seats_qty);
        $this->assertSame('cus_paid', $tenant->stripe_customer_id);
        $this->assertTrue($tenant->isPaidSubscriptionActive());
    }

    public function test_checkmate_seats_qty_syncs_to_stripe_subscription(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test',
        ]);

        $tenant = Tenant::factory()->create([
            'billing_plan' => 'checkmate',
            'billing_active_until' => now()->addDays(10),
            'billing_seats_qty' => 3,
            'stripe_customer_id' => 'cus_cm',
        ]);

        Http::fake([
            'api.stripe.com/v1/subscriptions?*' => Http::response([
                'data' => [[
                    'id' => 'sub_123',
                    'metadata' => ['tenant_id' => $tenant->id],
                    'items' => ['data' => [['id' => 'si_123', 'quantity' => 3]]],
                ]],
            ], 200),
            'api.stripe.com/v1/subscriptions/sub_123' => Http::response(['id' => 'sub_123'], 200),
        ]);

        app(UpdateBillingSeatsQtyAction::class)->handle($tenant, 8);

        $this->assertSame(8, $tenant->fresh()->billing_seats_qty);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/v1/subscriptions/sub_123')
                && ($data['items[0][id]'] ?? null) === 'si_123'
                && (int) ($data['items[0][quantity]'] ?? 0) === 8;
        });
    }

    public function test_checkmate_seats_qty_not_saved_when_stripe_sync_fails(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test',
        ]);

        $tenant = Tenant::factory()->create([
            'billing_plan' => 'checkmate',
            'billing_active_until' => now()->addDays(10),
            'billing_seats_qty' => 3,
            'stripe_customer_id' => 'cus_cm',
        ]);

        Http::fake([
            'api.stripe.com/v1/subscriptions*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        try {
            app(UpdateBillingSeatsQtyAction::class)->handle($tenant, 8);
            $this->fail('expected seats_qty_stripe_failed');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('seats_qty_stripe_failed', $e->getMessage());
        }

        $this->assertSame(3, $tenant->fresh()->billing_seats_qty);
    }
}