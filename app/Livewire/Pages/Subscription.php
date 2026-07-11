<?php

namespace App\Livewire\Pages;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\FulfillStripeCheckoutSessionAction;
use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Http\Requests\Billing\ActivateSubscriptionPlanRequest;
use App\Models\Tenant;
use App\Support\Billing\BillingCatalogViewData;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Subscription extends Component
{
    use AuthorizesRequests;

    public ?string $selectedPlan = null;

    public ?string $statusMessage = null;

    public function mount(FulfillStripeCheckoutSessionAction $fulfillStripe, RealignSubscriptionPeriodAction $realign): void
    {
        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $realign->handle($tenant);
            auth()->user()?->load('tenant');
        }

        $this->selectedPlan = auth()->user()?->tenant?->effectivePlanKey();

        if (request()->query('stripe') === 'cancel') {
            session()->flash('error', __('subscription.stripe.checkout_cancelled'));
        }

        $sessionId = request()->query('session_id');
        if (request()->query('stripe') === 'success' && is_string($sessionId) && $sessionId !== '') {
            if ($fulfillStripe->handle($sessionId)) {
                $this->selectedPlan = auth()->user()?->tenant?->fresh()?->effectivePlanKey();
                session()->flash('success', __('subscription.stripe.activated'));
            } else {
                session()->flash('warning', __('subscription.stripe.return_unconfirmed'));
            }
        }
    }

    public function startStripeCheckout(string $plan, StripeCheckoutService $stripe): void
    {
        $this->statusMessage = null;

        $tenant = auth()->user()->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        if (! auth()->user()->can('manageSubscription', $tenant)) {
            $this->addError('plan', __('subscription.errors.admin_only'));

            return;
        }

        $url = $stripe->createCheckoutSession(auth()->user(), $tenant, $plan);
        if ($url === null) {
            $this->addError('plan', __('subscription.stripe.not_configured'));

            return;
        }

        $this->redirect($url);
    }

    public function activatePlan(string $plan, ActivateSubscriptionPlanAction $activate): void
    {
        $this->statusMessage = null;

        $tenant = auth()->user()->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        if (! auth()->user()->can('manageSubscription', $tenant)) {
            $this->addError('plan', __('subscription.errors.admin_only'));

            return;
        }

        $request = new ActivateSubscriptionPlanRequest;
        $validated = validator(['plan' => $plan], $request->rules(), $request->messages())->validate();

        $planKey = $validated['plan'];
        $planConfig = config("billing.plans.{$planKey}");

        if (! ($planConfig['self_activate'] ?? false)) {
            $this->statusMessage = __('subscription.enterprise_contact', [
                'email' => config('billing.contact_email'),
            ]);

            return;
        }

        if (! config('billing.allow_tenant_self_activation', true)) {
            $this->addError('plan', __('subscription.errors.activation_disabled'));

            return;
        }

        if (app(StripeCheckoutService::class)->isConfiguredForPlan($planKey)) {
            $this->startStripeCheckout($planKey, app(StripeCheckoutService::class));

            return;
        }

        $activate->handle(auth()->user(), $tenant, $planKey, 'manual');

        $this->selectedPlan = $planKey;
        session()->flash('success', __('subscription.activated', ['plan' => __("subscription.plans.{$planKey}.name")]));
    }

    public function render(RealignSubscriptionPeriodAction $realign)
    {
        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
        }
        $billingStatus = match (true) {
            $tenant?->isLegacyWithoutBillingTracking() => 'legacy',
            $tenant?->isTrialActive() => 'trial',
            $tenant?->isPaidSubscriptionActive() => 'paid',
            $tenant?->isInPaidSubscriptionGrace() => 'grace',
            default => 'expired',
        };

        return view('livewire.pages.subscription', [
            ...BillingCatalogViewData::catalog(),
            'publicMode' => false,
            'tenant' => $tenant,
            'billingStatus' => $billingStatus,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'canManage' => $tenant && auth()->user()?->can('manageSubscription', $tenant),
            'selectedPlan' => $this->selectedPlan,
            'statusMessage' => $this->statusMessage,
        ]);
    }
}
