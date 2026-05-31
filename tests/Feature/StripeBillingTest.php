<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertFalse($service->isConfiguredForPlan('starter'));

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'starter', 'manual');

        $tenant->refresh();
        $this->assertSame('starter', $tenant->billing_plan);
        $this->assertTrue($tenant->isPaidSubscriptionActive());
        $this->assertTrue($tenant->billing_active_until->lte(now()->addDays(30)));
        $this->assertTrue($tenant->billing_active_until->gte(now()->addDays(29)));
    }

    public function test_realigns_starter_subscription_after_yearly_misactivation(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now(),
            'billing_plan' => 'starter',
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
}
