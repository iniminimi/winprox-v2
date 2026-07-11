@php
    $publicMode = $publicMode ?? false;
    $showManageActions = ($canManage ?? false) && ! $publicMode;
@endphp

<div class="wp-stack">
    <div @class(['wp-page-head', 'wp-billing-page-width' => $publicMode])>
        @if ($publicMode)
            <x-wp-page-head-title
                icon="subscription"
                :title="__('subscription.public_title')"
                :subtitle="__('subscription.public_subtitle')"
            />
        @else
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
        @endif
    </div>

    @if (! $publicMode)
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
                            $trialPlanKey = is_string($trialPlanKey) ? strtolower($trialPlanKey) : null;
                        @endphp
                        <li>{{ __('subscription.status_trial', [
                            'plan' => $trialPlanKey ? __("subscription.plans.{$trialPlanKey}.name") : '—',
                            'date' => $tenant->trial_ends_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—',
                        ]) }}</li>
                    @elseif ($billingStatus === 'paid')
                        @php
                            $displayPlanKey = is_string($tenant->billing_plan) ? strtolower($tenant->billing_plan) : null;
                        @endphp
                        <li>{{ __('subscription.status_active', [
                            'plan' => $displayPlanKey ? __("subscription.plans.{$displayPlanKey}.name") : '—',
                            'date' => $tenant->billing_active_until->timezone(config('app.timezone'))->format('d/m/Y'),
                        ]) }}</li>
                    @elseif ($billingStatus === 'grace')
                        @php
                            $displayPlanKey = is_string($tenant->billing_plan) ? strtolower($tenant->billing_plan) : null;
                        @endphp
                        <li class="wp-billing-status-list__warn">{{ __('subscription.status_grace', [
                            'plan' => $displayPlanKey ? __("subscription.plans.{$displayPlanKey}.name") : '—',
                            'ended' => $tenant->billing_active_until->timezone(config('app.timezone'))->format('d/m/Y'),
                            'grace_end' => $tenant->paidSubscriptionGraceEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y'),
                            'days' => (int) ($tenant->paidSubscriptionGraceBatteryState()['days_remaining'] ?? 0),
                        ]) }}</li>
                    @else
                        <li class="wp-billing-status-list__warn">{{ __('subscription.status_expired') }}</li>
                    @endif
                    @if ($tenant->has_esg_module)
                        <li>{{ __('subscription.status_module_esg') }}</li>
                    @endif
                    @if ($tenant->has_time_module)
                        <li>{{ __('subscription.status_module_time') }}</li>
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
                    $limitMaxDocuments = $tenant->maxDocumentsOrgLimit();
                    $limitShowDocuments = $limitMaxDocuments !== null
                        && $tenant->isAtDocumentsOrgLimit()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                @endphp
                @if ($limitShowUnits || $limitShowUsers || $limitShowDocuments)
                    <div class="wp-flash wp-flash--muted">
                        <p class="wp-section-title">{{ __('subscription.page_limits_title') }}</p>
                        <ul class="wp-billing-status-list">
                            @if ($limitShowUnits)
                                <li>{{ __('subscription.page_limits_units_body', ['max' => $limitMaxUnits, 'current' => $tenant->currentUnitsCount()]) }}</li>
                            @endif
                            @if ($limitShowUsers)
                                <li>{{ __('subscription.page_limits_users_body', ['max' => $limitMaxUsers, 'current' => $tenant->currentUsersCount()]) }}</li>
                            @endif
                            @if ($limitShowDocuments)
                                <li>{{ __('subscription.page_limits_documents_body', ['max' => $limitMaxDocuments, 'current' => $tenant->currentDocumentsCount()]) }}</li>
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
    @endif

    <section class="wp-billing-product wp-card wp-card-pad">
        <figure class="wp-billing-product__logo">
            <img
                src="{{ asset('images/welcome/winprox_time_logo.jpg') }}"
                alt="{{ __('welcome.products.time.logo_alt') }}"
                class="wp-billing-product__logo-img"
                loading="lazy"
                decoding="async"
            >
        </figure>
        <div class="wp-billing-product__content">
            <header class="wp-billing-product__intro">
                <h2 class="wp-section-title">{{ __('subscription.products.time.heading') }}</h2>
                <p class="wp-muted">{{ __('subscription.products.time.intro') }}</p>
            </header>

            <div class="wp-billing-plan-list">
                <article class="wp-billing-plan-card" wire:key="subscription-product-time">
                    <div class="wp-billing-plan-card-body">
                        <div class="wp-billing-plan-card-head">
                            <h2 class="wp-billing-plan-card-title">{{ __('subscription.products.time.name') }}</h2>
                            <p class="wp-billing-plan-card-price">{{ __('subscription.products.time.price') }}</p>
                        </div>
                        <p class="wp-billing-plan-card-minimum">{{ __('subscription.products.time.includes') }}</p>
                        <ul class="wp-billing-plan-card-meta">
                            @foreach (__('subscription.products.time.bullets') as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                        <p class="wp-billing-plan-card-desc">{{ __('subscription.products.time.description') }}</p>
                    </div>
                    @if ($showManageActions)
                        <div class="wp-billing-plan-card-action">
                            <a
                                href="{{ route('contact.index') }}"
                                class="btn btn--primary btn--block"
                            >
                                {{ __('subscription.products.time.contact_cta') }}
                            </a>
                        </div>
                    @endif
                </article>
            </div>
        </div>
    </section>

    <section class="wp-billing-product wp-card wp-card-pad">
        <figure class="wp-billing-product__logo">
            <img
                src="{{ asset('images/welcome/winprox_facility_logo.jpg') }}"
                alt="{{ __('welcome.products.facility.logo_alt') }}"
                class="wp-billing-product__logo-img"
                loading="lazy"
                decoding="async"
            >
        </figure>
        <div class="wp-billing-product__content">
            <header class="wp-billing-product__intro">
                <h2 class="wp-section-title">{{ __('subscription.facility_heading') }}</h2>
                <p class="wp-muted">{{ __('subscription.facility_intro') }}</p>
            </header>

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
                                @if (__("subscription.plans.{$planKey}.announcements") !== "subscription.plans.{$planKey}.announcements")
                                    <li>{{ __("subscription.plans.{$planKey}.announcements") }}</li>
                                @endif
                                @if (__("subscription.plans.{$planKey}.documents") !== "subscription.plans.{$planKey}.documents")
                                    <li>{{ __("subscription.plans.{$planKey}.documents") }}</li>
                                @endif
                            </ul>
                            <p class="wp-billing-plan-card-desc">{{ __("subscription.plans.{$planKey}.description") }}</p>
                        </div>
                        @if ($showManageActions)
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
                                        <x-wp-spinner wire:loading class="wp-mr-2" />
                                        <span wire:loading.remove>{{ __('subscription.choose_plan') }}</span>
                                        <span wire:loading>{{ __('subscription.choose_plan_loading') }}</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        class="btn btn--primary btn--block"
                                        wire:click="activatePlan('{{ $planKey }}')"
                                        wire:loading.attr="disabled"
                                    >
                                        <x-wp-spinner wire:loading class="wp-mr-2" />
                                        <span wire:loading.remove>{{ __('subscription.choose_plan') }}</span>
                                        <span wire:loading>{{ __('subscription.choose_plan_loading') }}</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <div class="wp-card wp-card-pad wp-stack-tight wp-billing-page-width">
        <h2 class="wp-section-title">{{ __('subscription.modules_heading') }}</h2>
        <p class="wp-muted">{{ __('subscription.modules_intro') }}</p>
    </div>

    @foreach ($moduleKeys as $moduleKey)
        @php
            $isModuleActive = $moduleKey === 'esg'
                ? (bool) $tenant?->has_esg_module
                : (bool) $tenant?->has_time_module;
            $modulePlanTiers = config("billing.modules.{$moduleKey}.plan_tiers", ['pro', 'business']);
        @endphp
        <section class="wp-billing-product wp-billing-product--module wp-card wp-card-pad" wire:key="subscription-module-section-{{ $moduleKey }}">
            <figure class="wp-billing-product__logo">
                <img
                    src="{{ asset('images/welcome/winprox_'.$moduleKey.'_module_logo.jpg') }}"
                    alt="{{ __('subscription.modules.'.$moduleKey.'.name') }}"
                    class="wp-billing-product__logo-img"
                    loading="lazy"
                    decoding="async"
                >
            </figure>
            <div class="wp-billing-product__content">
                <header class="wp-billing-product__intro">
                    <h2 class="wp-section-title">{{ __("subscription.modules.{$moduleKey}.name") }}</h2>
                    <p class="wp-muted">{{ __("subscription.modules.{$moduleKey}.description") }}</p>
                </header>

                <div class="wp-billing-plan-list">
                    <article class="wp-billing-plan-card" wire:key="subscription-module-{{ $moduleKey }}">
                        <div class="wp-billing-plan-card-body">
                            <p class="wp-billing-plan-card-minimum">{{ __("subscription.modules.{$moduleKey}.pricing_caption") }}</p>
                            <ul class="wp-billing-module-pricing">
                                @foreach ($modulePlanTiers as $planTier)
                                    <li>
                                        <span class="wp-billing-module-pricing__plan">{{ __("subscription.plans.{$planTier}.name") }}</span>
                                        <span class="wp-billing-module-pricing__price">{{ __("subscription.modules.{$moduleKey}.pricing.{$planTier}") }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <ul class="wp-billing-plan-card-meta">
                                @foreach (__("subscription.modules.{$moduleKey}.bullets") as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @if ($showManageActions)
                            <div class="wp-billing-plan-card-action">
                                @if ($isModuleActive)
                                    <span class="wp-pill wp-pill--done">{{ __('subscription.module_active') }}</span>
                                @else
                                    <a
                                        href="{{ route('contact.index') }}"
                                        class="btn btn--primary btn--block"
                                    >
                                        {{ __('subscription.module_contact_cta') }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    </article>
                </div>
            </div>
        </section>
    @endforeach

    <p class="wp-billing-legal">
        <span>{{ __('subscription.legal_footer_intro') }}</span>
        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_terms') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_privacy') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.dpa') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_dpa') }}</a>.
    </p>
    <p class="wp-billing-legal wp-billing-legal--hint">{{ __('legal.inline_jurisdiction_hint') }}</p>
    @if (! $publicMode)
        <p class="wp-billing-legal wp-billing-legal--notice">
            @if ($stripeLive)
                {{ __('subscription.stripe_notice_live') }}
            @else
                {{ __('subscription.stripe_notice_simulated') }}
            @endif
        </p>
    @endif
</div>
