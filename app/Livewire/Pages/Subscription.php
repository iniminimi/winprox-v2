<?php

namespace App\Livewire\Pages;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Http\Requests\Billing\ActivateSubscriptionPlanRequest;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Subscription extends Component
{
    public ?string $selectedPlan = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->selectedPlan = auth()->user()?->tenant?->effectivePlanKey();
    }

    public function activatePlan(string $plan, ActivateSubscriptionPlanAction $activate): void
    {
        $this->statusMessage = null;

        if (! auth()->user()?->isAdmin()) {
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

        $tenant = auth()->user()->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $activate->handle(auth()->user(), $tenant, $planKey);

        $this->selectedPlan = $planKey;
        $this->statusMessage = __('subscription.activated', ['plan' => __("subscription.plans.{$planKey}.name")]);
    }

    public function render()
    {
        $tenant = auth()->user()?->tenant;
        $plans = config('billing.plans', []);

        return view('livewire.pages.subscription', [
            'tenant' => $tenant,
            'plans' => $plans,
            'unitsCount' => $tenant?->currentUnitsCount() ?? 0,
            'usersCount' => $tenant?->currentUsersCount() ?? 0,
        ]);
    }
}
