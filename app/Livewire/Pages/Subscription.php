<?php

namespace App\Livewire\Pages;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Billing\FulfillStripeCheckoutSessionAction;
use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Actions\TenantPurge\CancelTenantPurgeRequestAction;
use App\Actions\TenantPurge\ExecuteTenantPurgeAction;
use App\Actions\TenantPurge\StartTenantPurgeRequestAction;
use App\Enums\TenantPurgeStatus;
use App\Http\Requests\Billing\ActivateSubscriptionPlanRequest;
use App\Http\Requests\TenantPurge\StartTenantPurgeRequest;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Support\Billing\BillingCatalogViewData;
use App\Support\Platform\SupportTenantContext;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    public string $purgePassword = '';

    public bool $purgeExportAck = false;

    public string $purgeExecutePassword = '';

    /** @var null|'start'|'execute_trial'|'execute_paid'|'cancel' */
    public ?string $purgeConfirmKind = null;

    public function mount(FulfillStripeCheckoutSessionAction $fulfillStripe, RealignSubscriptionPeriodAction $realign): void
    {
        $tenant = $this->resolveTenant();
        if ($tenant !== null) {
            $realign->handle($tenant);
        }

        $this->selectedPlan = $this->resolveTenant()?->effectivePlanKey();

        if (request()->query('stripe') === 'cancel') {
            session()->flash('error', __('subscription.stripe.checkout_cancelled'));
        }

        $sessionId = request()->query('session_id');
        if (request()->query('stripe') === 'success' && is_string($sessionId) && $sessionId !== '') {
            if ($fulfillStripe->handle($sessionId)) {
                $this->selectedPlan = $this->resolveTenant()?->fresh()?->effectivePlanKey();
                session()->flash('success', __('subscription.stripe.activated'));
            } else {
                session()->flash('warning', __('subscription.stripe.return_unconfirmed'));
            }
        }
    }

    public function startStripeCheckout(string $plan, StripeCheckoutService $stripe): void
    {
        $this->statusMessage = null;

        $tenant = $this->resolveTenant();
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

        $tenant = $this->resolveTenant();
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

    public function preparePurgeConfirm(string $kind): void
    {
        if (! in_array($kind, ['start', 'execute_trial', 'execute_paid', 'cancel'], true)) {
            return;
        }

        $this->resetErrorBag('purge', 'purge_password', 'purge_export_ack');

        if ($kind === 'start') {
            $form = new StartTenantPurgeRequest;
            try {
                validator(
                    [
                        'purge_password' => $this->purgePassword,
                        'purge_export_ack' => $this->purgeExportAck,
                    ],
                    $form::rules(),
                    $form->messages(),
                )->validate();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($key, $message);
                    }
                }

                return;
            }

            if (! Hash::check($this->purgePassword, (string) auth()->user()?->password)) {
                $this->addError('purge_password', __('subscription.purge.errors.password'));

                return;
            }
        }

        if ($kind === 'execute_trial') {
            if (trim($this->purgeExecutePassword) === '') {
                $this->addError('purge_password', __('subscription.purge.errors.password_required'));

                return;
            }

            if (! Hash::check($this->purgeExecutePassword, (string) auth()->user()?->password)) {
                $this->addError('purge_password', __('subscription.purge.errors.password'));

                return;
            }
        }

        $this->purgeConfirmKind = $kind;
    }

    public function dismissPurgeConfirm(): void
    {
        $this->purgeConfirmKind = null;
    }

    public function confirmPurgeAction(
        StartTenantPurgeRequestAction $start,
        CancelTenantPurgeRequestAction $cancel,
        ExecuteTenantPurgeAction $execute,
    ): void {
        $kind = $this->purgeConfirmKind;
        $this->dismissPurgeConfirm();

        match ($kind) {
            'start' => $this->startPurgeRequest($start),
            'execute_trial' => $this->executeTrialPurge($execute),
            'execute_paid' => $this->executePaidPurge($execute),
            'cancel' => $this->cancelPurgeRequest($cancel),
            default => null,
        };
    }

    public function startPurgeRequest(StartTenantPurgeRequestAction $start): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('requestTenantPurge', $tenant);

        $form = new StartTenantPurgeRequest;
        try {
            $validated = validator(
                [
                    'purge_password' => $this->purgePassword,
                    'purge_export_ack' => $this->purgeExportAck,
                ],
                $form::rules(),
                $form->messages(),
            )->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        try {
            $start->handle(
                $tenant,
                auth()->user(),
                $validated['purge_password'],
                (bool) $validated['purge_export_ack'],
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->purgePassword = '';
        $this->purgeExportAck = false;
        session()->flash('success', __('subscription.purge.started'));
    }

    public function cancelPurgeRequest(CancelTenantPurgeRequestAction $cancel): void
    {
        $tenant = $this->resolveTenant();
        $purge = $this->openPurgeRequest($tenant);
        if ($tenant === null || $purge === null) {
            return;
        }

        $this->authorize('cancelTenantPurge', $tenant);

        try {
            $cancel->handle($purge, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('purge', collect($e->errors())->flatten()->first() ?? __('subscription.purge.errors.generic'));

            return;
        }

        session()->flash('success', __('subscription.purge.cancelled'));
    }

    public function executeTrialPurge(ExecuteTenantPurgeAction $execute): void
    {
        $tenant = $this->resolveTenant();
        $purge = $this->openPurgeRequest($tenant);
        if ($tenant === null || $purge === null) {
            return;
        }

        $this->authorize('executeTrialTenantPurge', $tenant);

        try {
            $execute->handle($purge, auth()->user(), $this->purgeExecutePassword);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->purgeExecutePassword = '';
        session()->flash('success', __('subscription.purge.completed'));
        $this->redirect(route('login'));
    }

    public function executePaidPurge(ExecuteTenantPurgeAction $execute): void
    {
        $tenant = $this->resolveTenant();
        $purge = $this->openPurgeRequest($tenant);
        if ($tenant === null || $purge === null) {
            return;
        }

        $this->authorize('executePaidTenantPurge', $tenant);

        try {
            $execute->handle($purge, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('purge', collect($e->errors())->flatten()->first() ?? __('subscription.purge.errors.generic'));

            return;
        }

        session()->flash('success', __('subscription.purge.completed_superuser'));
        $this->redirect(route('platform.tenants'));
    }

    public function render(RealignSubscriptionPeriodAction $realign)
    {
        $tenant = $this->resolveTenant();
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

        $purgeRequest = $this->openPurgeRequest($tenant);
        $user = auth()->user();

        return view('livewire.pages.subscription', [
            ...BillingCatalogViewData::catalog(),
            'publicMode' => false,
            'tenant' => $tenant,
            'billingStatus' => $billingStatus,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'canManage' => $tenant && $user?->can('manageSubscription', $tenant),
            'selectedPlan' => $this->selectedPlan,
            'statusMessage' => $this->statusMessage,
            'purgeRequest' => $purgeRequest,
            'purgeTrack' => $tenant?->purgeTrack(),
            'canRequestPurge' => $tenant && $user?->can('requestTenantPurge', $tenant),
            'canCancelPurge' => $tenant && $purgeRequest && $user?->can('cancelTenantPurge', $tenant),
            'canExecuteTrialPurge' => $tenant
                && $purgeRequest?->status === TenantPurgeStatus::Ready
                && $user?->can('executeTrialTenantPurge', $tenant),
            'canExecutePaidPurge' => $tenant
                && $purgeRequest?->status === TenantPurgeStatus::Scheduled
                && $purgeRequest->scheduled_purge_at !== null
                && ! $purgeRequest->scheduled_purge_at->isFuture()
                && $user?->can('executePaidTenantPurge', $tenant),
            'purgeConfirmKind' => $this->purgeConfirmKind,
        ]);
    }

    private function resolveTenant(): ?Tenant
    {
        if (auth()->user()?->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return auth()->user()?->tenant;
    }

    private function openPurgeRequest(?Tenant $tenant): ?TenantPurgeRequest
    {
        if ($tenant === null) {
            return null;
        }

        return TenantPurgeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                TenantPurgeStatus::AwaitingEmail->value,
                TenantPurgeStatus::Ready->value,
                TenantPurgeStatus::Scheduled->value,
            ])
            ->latest('id')
            ->first();
    }
}
