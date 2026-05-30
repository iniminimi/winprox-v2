<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('subscription.title') }}</h1>
        <p class="wp-muted">{{ __('subscription.subtitle') }}</p>
    </div>

    @if ($statusMessage)
        <div class="wp-card wp-card-pad">
            <p>{{ $statusMessage }}</p>
        </div>
    @endif

    @if ($tenant && ($tenant->unitLimitWarning() || $tenant->userLimitWarning()))
        <div class="wp-flash {{ ($tenant->unitLimitWarning() === 'critical' || $tenant->userLimitWarning() === 'critical') ? 'wp-flash--danger' : 'wp-flash--muted' }}">
            @if ($warning = $tenant->unitLimitWarning())
                <p>{{ $warning === 'critical' ? __('subscription.limits.critical_units', ['remaining' => $tenant->remainingUnitSlots() ?? 0]) : __('subscription.limits.warning_units', ['remaining' => $tenant->remainingUnitSlots() ?? 0]) }}</p>
            @endif
            @if ($warning = $tenant->userLimitWarning())
                <p>{{ $warning === 'critical' ? __('subscription.limits.critical_users', ['remaining' => $tenant->remainingUserSlots() ?? 0]) : __('subscription.limits.warning_users', ['remaining' => $tenant->remainingUserSlots() ?? 0]) }}</p>
            @endif
        </div>
    @endif

    @error('plan')
        <p class="wp-form-error">{{ $message }}</p>
    @enderror

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('subscription.status.title') }}</h2>
        <dl class="wp-dl">
            <div class="wp-dl-row">
                <dt>{{ __('subscription.status.access') }}</dt>
                <dd>
                    @if ($tenant?->hasFullAppAccess())
                        <span class="wp-pill wp-pill--done">{{ __('subscription.status.access_ok') }}</span>
                    @else
                        <span class="wp-pill wp-pill--closed">{{ __('subscription.status.access_blocked') }}</span>
                    @endif
                </dd>
            </div>
            @if ($tenant?->isTrialActive())
                <div class="wp-dl-row">
                    <dt>{{ __('subscription.status.trial') }}</dt>
                    <dd>{{ __('subscription.status.trial_days', ['days' => $tenant->trialDaysRemaining()]) }}</dd>
                </div>
            @endif
            @if ($tenant?->billing_plan)
                <div class="wp-dl-row">
                    <dt>{{ __('subscription.status.plan') }}</dt>
                    <dd>{{ __('subscription.plans.' . $tenant->billing_plan . '.name') }}</dd>
                </div>
            @endif
            @if ($tenant?->billing_active_until)
                <div class="wp-dl-row">
                    <dt>{{ __('subscription.status.active_until') }}</dt>
                    <dd>{{ $tenant->billing_active_until->format('d-m-Y') }}</dd>
                </div>
            @endif
            <div class="wp-dl-row">
                <dt>{{ __('subscription.status.usage') }}</dt>
                <dd>{{ __('subscription.status.usage_counts', ['units' => $unitsCount, 'users' => $usersCount]) }}</dd>
            </div>
        </dl>
    </div>

    <div class="wp-stack">
        <h2 class="wp-section-title">{{ __('subscription.plans_heading') }}</h2>
        <div class="wp-plan-grid">
            @foreach ($plans as $planKey => $plan)
                @php
                    $isEnterprise = ! ($plan['self_activate'] ?? false);
                    $isCurrent = $selectedPlan === $planKey;
                @endphp
                <div class="wp-card wp-card-pad wp-plan-card {{ $isCurrent ? 'is-current' : '' }}">
                    <h3 class="wp-plan-name">{{ __("subscription.plans.{$planKey}.name") }}</h3>
                    <p class="wp-muted">{{ __("subscription.plans.{$planKey}.description") }}</p>
                    <ul class="wp-plan-limits">
                        <li>{{ __('subscription.limits.units', ['count' => $plan['units_limit'] ?? __('subscription.limits.unlimited')]) }}</li>
                        <li>{{ __('subscription.limits.users', ['count' => $plan['users_limit'] ?? __('subscription.limits.unlimited')]) }}</li>
                    </ul>

                    @if ($isEnterprise)
                        <p class="wp-muted">{{ __('subscription.enterprise_hint') }}</p>
                        <a href="mailto:{{ config('billing.contact_email') }}?subject={{ rawurlencode(__('subscription.enterprise_mail_subject')) }}"
                           class="btn btn--primary btn--block">
                            {{ __('subscription.enterprise_cta') }}
                        </a>
                    @elseif ($tenant && auth()->user()?->can('manageSubscription', $tenant))
                        @if (! config('stripe.enabled') || ! app(\App\Services\Billing\StripeCheckoutService::class)->isConfiguredForPlan($planKey))
                            <p class="wp-hint">{{ __('subscription.stripe.simulated_hint') }}</p>
                        @endif
                        <button type="button"
                                class="btn btn--primary btn--block"
                                wire:click="activatePlan('{{ $planKey }}')"
                                wire:loading.attr="disabled">
                            {{ $isCurrent ? __('subscription.current_plan') : __('subscription.activate_plan') }}
                        </button>
                    @else
                        <p class="wp-muted">{{ __('subscription.admin_required') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
