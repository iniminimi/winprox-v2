<div class="wp-stack">
    <div class="wp-page-head">
        <x-wp-page-head-title
            icon="subscription"
            :title="__('subscription.title')"
            help-page="subscription"
            :subtitle="$billingStatus === 'paid' ? null : __('subscription.subtitle')"
        >
            @if ($portalBatteryState)
                <x-slot:toolbar>
                    <x-wp-trial-battery-capsule :state="$portalBatteryState" />
                </x-slot:toolbar>
            @endif
        </x-wp-page-head-title>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="wp-flash wp-flash--danger">{{ session('error') }}</div>
    @endif

    @if (session('warning'))
        <div class="wp-flash wp-flash--muted">{{ session('warning') }}</div>
    @endif

    @if ($statusMessage)
        <div class="wp-flash wp-flash--muted">
            <p>{{ $statusMessage }}</p>
        </div>
    @endif

    @error('plan')
        <p class="wp-form-error">{{ $message }}</p>
    @enderror

    @if ($tenant)
        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="subscription-status-{{ $billingStatus }}-{{ $tenant->billing_plan }}-{{ $tenant->billing_active_until?->timestamp }}">
            <p class="wp-section-title">{{ __('subscription.status_heading') }}</p>
            <ul class="wp-billing-status-list">
                @if ($billingStatus === 'legacy')
                    <li>{{ __('subscription.status_legacy') }}</li>
                @elseif ($billingStatus === 'trial')
                    @php
                        $trialPlanKey = $tenant->effectivePlanKey();
                    @endphp
                    <li>{{ __('subscription.status_trial', [
                        'plan' => $trialPlanKey ? __("subscription.plans.{$trialPlanKey}.name") : '—',
                        'date' => $tenant->trial_ends_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—',
                    ]) }}</li>
                @elseif ($billingStatus === 'paid')
                    <li>{{ __('subscription.status_active', [
                        'plan' => __("subscription.plans.{$tenant->billing_plan}.name"),
                        'date' => $tenant->billing_active_until->timezone(config('app.timezone'))->format('d/m/Y'),
                    ]) }}</li>
                @elseif ($billingStatus === 'grace')
                    <li class="wp-billing-status-list__warn">{{ __('subscription.status_grace', [
                        'plan' => __("subscription.plans.{$tenant->billing_plan}.name"),
                        'ended' => $tenant->billing_active_until->timezone(config('app.timezone'))->format('d/m/Y'),
                        'grace_end' => $tenant->paidSubscriptionGraceEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y'),
                        'days' => (int) ($tenant->paidSubscriptionGraceBatteryState()['days_remaining'] ?? 0),
                    ]) }}</li>
                @else
                    <li class="wp-billing-status-list__warn">{{ __('subscription.status_expired') }}</li>
                @endif
            </ul>
            @if (! in_array($billingStatus, ['paid', 'grace'], true))
                <p class="wp-muted">{{ __('subscription.pricing_intro') }}</p>
            @endif
        </div>

        @if (! $tenant->isLegacyWithoutBillingTracking())
            @php
                $limitMaxUnits = $tenant->maxUnitsLimit();
                $limitMaxUsers = $tenant->maxUsersLimit();
                $limitShowUnits = $limitMaxUnits !== null
                    && $tenant->isAtUnitLimit()
                    && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                $limitShowUsers = $limitMaxUsers !== null
                    && ! $tenant->canAddUser()
                    && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
            @endphp
            @if ($limitShowUnits || $limitShowUsers)
                <div class="wp-flash wp-flash--muted">
                    <p class="wp-section-title">{{ __('subscription.page_limits_title') }}</p>
                    <ul class="wp-billing-status-list">
                        @if ($limitShowUnits)
                            <li>{{ __('subscription.page_limits_units_body', ['max' => $limitMaxUnits, 'current' => $tenant->currentUnitsCount()]) }}</li>
                        @endif
                        @if ($limitShowUsers)
                            <li>{{ __('subscription.page_limits_users_body', ['max' => $limitMaxUsers, 'current' => $tenant->currentUsersCount()]) }}</li>
                        @endif
                    </ul>
                </div>
            @endif
        @endif
    @endif

    @if ($tenant && ! $canManage)
        <div class="wp-flash wp-flash--muted">
            {{ __('subscription.admin_required') }}
        </div>
    @endif

    <div class="wp-billing-plan-list">
        @foreach ($planKeys as $planKey)
            @php
                $isEnterprise = $planKey === 'enterprise';
            @endphp
            <article class="wp-billing-plan-card">
                <div class="wp-billing-plan-card-body">
                    <div class="wp-billing-plan-card-head">
                        <h2 class="wp-billing-plan-card-title">{{ __("subscription.plans.{$planKey}.name") }}</h2>
                        <p class="wp-billing-plan-card-price">{{ __("subscription.plans.{$planKey}.price") }}</p>
                    </div>
                    <ul class="wp-billing-plan-card-meta">
                        <li>{{ __("subscription.plans.{$planKey}.units") }}</li>
                        <li>{{ __("subscription.plans.{$planKey}.users") }}</li>
                    </ul>
                    <p class="wp-billing-plan-card-desc">{{ __("subscription.plans.{$planKey}.description") }}</p>
                </div>
                @if ($canManage)
                    <div class="wp-billing-plan-card-action">
                        @if ($isEnterprise)
                            <a
                                href="{{ route('contact.index') }}"
                                class="btn btn--primary btn--block"
                            >
                                {{ __('subscription.enterprise_cta') }}
                            </a>
                        @elseif (in_array($planKey, $stripeReadyPlans, true))
                            <button
                                type="button"
                                class="btn btn--primary btn--block"
                                wire:click="activatePlan('{{ $planKey }}')"
                                wire:loading.attr="disabled"
                            >
                                {{ __('subscription.choose_plan') }}
                            </button>
                        @else
                            <button
                                type="button"
                                class="btn btn--primary btn--block"
                                wire:click="activatePlan('{{ $planKey }}')"
                                wire:loading.attr="disabled"
                            >
                                {{ __('subscription.choose_plan') }}
                            </button>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <p class="wp-billing-legal">
        <span>{{ __('subscription.legal_footer_intro') }}</span>
        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_terms') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_privacy') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.dpa') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_dpa') }}</a>.
    </p>
    <p class="wp-billing-legal wp-billing-legal--hint">{{ __('legal.inline_jurisdiction_hint') }}</p>
    <p class="wp-billing-legal wp-billing-legal--notice">
        @if ($stripeLive)
            {{ __('subscription.stripe_notice_live') }}
        @else
            {{ __('subscription.stripe_notice_simulated') }}
        @endif
    </p>
</div>
