<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\ExtendPaidSubscriptionFromStripeAction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }
}