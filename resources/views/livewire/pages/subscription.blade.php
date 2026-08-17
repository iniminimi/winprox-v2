@php
    $publicMode = $publicMode ?? false;
    $showManageActions = ($canManage ?? false) && ! $publicMode;
@endphp

<div class="wp-stack" data-manual-capture="subscription">
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
            <p class="wp-error">{{ $message }}</p>
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
                    @if ($tenant->has_iot_module)
                        <li>{{ __('subscription.status_module_iot') }}</li>
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
                    $limitMaxLocations = $tenant->maxLocationsLimit();
                    $limitMaxUsers = $tenant->maxUsersLimit();
                    $limitShowUnits = $limitMaxUnits !== null
                        && $tenant->isAtUnitLimit()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                    $limitShowLocations = $limitMaxLocations !== null
                        && $tenant->isAtLocationLimit()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                    $limitShowUsers = $limitMaxUsers !== null
                        && ! $tenant->canAddUser()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                    $limitMaxDocuments = $tenant->maxDocumentsOrgLimit();
                    $limitShowDocuments = $limitMaxDocuments !== null
                        && $tenant->isAtDocumentsOrgLimit()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                    $limitMaxPhotos = $tenant->maxPhotosOrgLimit();
                    $limitShowPhotos = $limitMaxPhotos !== null
                        && $tenant->isAtPhotosOrgLimit()
                        && in_array($billingStatus, ['trial', 'paid', 'grace'], true);
                @endphp
                @if ($limitShowUnits || $limitShowLocations || $limitShowUsers || $limitShowDocuments || $limitShowPhotos)
                    <div class="wp-flash wp-flash--muted">
                        <p class="wp-section-title">{{ __('subscription.page_limits_title') }}</p>
                        <ul class="wp-billing-status-list">
                            @if ($limitShowLocations)
                                <li>{{ __('subscription.page_limits_locations_body', ['max' => $limitMaxLocations, 'current' => $tenant->currentLocationsCount()]) }}</li>
                            @endif
                            @if ($limitShowUnits)
                                <li>{{ __('subscription.page_limits_units_body', ['max' => $limitMaxUnits, 'current' => $tenant->currentUnitsCount()]) }}</li>
                            @endif
                            @if ($limitShowUsers)
                                <li>{{ __('subscription.page_limits_users_body', ['max' => $limitMaxUsers, 'current' => $tenant->currentUsersCount()]) }}</li>
                            @endif
                            @if ($limitShowDocuments)
                                <li>{{ __('subscription.page_limits_documents_body', ['max' => $limitMaxDocuments, 'current' => $tenant->currentDocumentsCount()]) }}</li>
                            @endif
                            @if ($limitShowPhotos)
                                <li>{{ __('subscription.page_limits_photos_body', ['max' => $limitMaxPhotos, 'current' => $tenant->currentPhotosCount()]) }}</li>
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

    <section class="wp-card wp-card-pad wp-billing-page-width">
        <header class="wp-billing-product__intro wp-stack-tight">
            <h2 class="wp-section-title">{{ __('subscription.plans_heading') }}</h2>
            <p class="wp-muted">{{ __('subscription.plans_intro') }}</p>
        </header>

        @if ($publicMode)
            <div class="wp-billing-comparison wp-stack-tight">
                <h3 class="wp-subhead">{{ __('subscription.comparison_heading') }}</h3>
                <div class="wp-billing-comparison-scroll">
                    <table class="wp-billing-comparison-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('subscription.comparison_col_plan') }}</th>
                                <th scope="col">{{ __('subscription.comparison_col_price') }}</th>
                                <th scope="col">{{ __('subscription.comparison_col_units') }}</th>
                                <th scope="col">{{ __('subscription.comparison_col_documents') }}</th>
                                <th scope="col">{{ __('subscription.comparison_col_time') }}</th>
                                <th scope="col">{{ __('subscription.comparison_col_api') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row">{{ __('subscription.plans.trial.name') }}</th>
                                <td>{{ __('subscription.comparison_trial_price') }}</td>
                                <td>50</td>
                                <td>50</td>
                                <td>{{ __('subscription.comparison_no') }}</td>
                                <td>{{ __('subscription.comparison_no') }}</td>
                            </tr>
                            @foreach ($planKeys as $planKey)
                                @php
                                    $planConfig = config("billing.plans.{$planKey}", []);
                                    $unitsLimit = $planConfig['units_limit'] ?? null;
                                    $docsLimit = $planConfig['documents_org_limit'] ?? null;
                                    $hasTimeVariant = is_string($planConfig['time_variant'] ?? null);
                                @endphp
                                <tr>
                                    <th scope="row">{{ __("subscription.plans.{$planKey}.name") }}</th>
                                    <td>{{ __("subscription.plans.{$planKey}.price") }}</td>
                                    <td>
                                        @if ($unitsLimit !== null)
                                            {{ number_format((int) $unitsLimit, 0, ',', '.') }}
                                        @else
                                            {{ __('subscription.comparison_custom') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($docsLimit !== null)
                                            {{ number_format((int) $docsLimit, 0, ',', '.') }}
                                        @else
                                            {{ __('subscription.comparison_custom') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (! empty($planConfig['time_module']))
                                            {{ __('subscription.comparison_yes') }}
                                        @elseif ($hasTimeVariant)
                                            {{ __('subscription.comparison_time_optional') }}
                                        @else
                                            {{ __('subscription.comparison_no') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (! empty($planConfig['api_access']))
                                            {{ __('subscription.comparison_yes') }}
                                        @else
                                            {{ __('subscription.comparison_no') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="wp-muted wp-text-sm">{{ __('subscription.comparison_note') }}</p>
            </div>
        @endif

        <div class="wp-billing-plan-list">
            @foreach ($planKeys as $planKey)
                @php
                    $planConfig = config("billing.plans.{$planKey}", []);
                    $timeVariant = $planConfig['time_variant'] ?? null;
                    $timeMonthly = $planConfig['time_monthly_eur'] ?? null;
                    $isCurrentPlan = ! $publicMode && (
                        ($selectedPlan ?? null) === $planKey
                        || (is_string($timeVariant) && ($selectedPlan ?? null) === $timeVariant)
                    );
                @endphp
                <article class="wp-billing-plan-card" wire:key="subscription-plan-{{ $planKey }}">
                    <div class="wp-billing-plan-card-body">
                        <div class="wp-billing-plan-card-head">
                            <h2 class="wp-billing-plan-card-title">{{ __("subscription.plans.{$planKey}.name") }}</h2>
                            <p class="wp-billing-plan-card-price">{{ __("subscription.plans.{$planKey}.price") }}</p>
                        </div>
                        <ul class="wp-billing-plan-card-meta wp-billing-plan-card-bullets">
                            @if (__("subscription.plans.{$planKey}.scale") !== "subscription.plans.{$planKey}.scale")
                                <li>{{ __("subscription.plans.{$planKey}.scale") }}</li>
                            @else
                                <li>{{ __("subscription.plans.{$planKey}.units") }}</li>
                                <li>{{ __("subscription.plans.{$planKey}.documents") }}</li>
                                <li>{{ __("subscription.plans.{$planKey}.photos") }}</li>
                            @endif
                            @if (__("subscription.plans.{$planKey}.users") !== "subscription.plans.{$planKey}.users")
                                <li>{{ __("subscription.plans.{$planKey}.users") }}</li>
                            @endif
                            @if (is_array(__("subscription.plans.{$planKey}.features")))
                                @foreach (__("subscription.plans.{$planKey}.features") as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            @endif
                            @if (__("subscription.plans.{$planKey}.description") !== "subscription.plans.{$planKey}.description")
                                <li>{{ __("subscription.plans.{$planKey}.description") }}</li>
                            @endif
                        </ul>
                        @if ($showManageActions && is_string($timeVariant) && $timeVariant !== '')
                            <label class="wp-check wp-billing-time-addon">
                                <input type="checkbox" wire:model.live="includeTime.{{ $planKey }}">
                                <span>
                                    {{ __('subscription.time_addon.label') }}
                                    @if (is_numeric($timeMonthly))
                                        — {{ __('subscription.time_addon.monthly', ['price' => '€'.(int) $timeMonthly]) }}
                                    @endif
                                </span>
                            </label>
                            <p class="wp-muted wp-text-sm">{{ __('subscription.time_addon.hint') }}</p>
                        @elseif ($publicMode && is_string($timeVariant) && $timeVariant !== '')
                            <p class="wp-muted wp-text-sm">{{ __('subscription.time_addon.public_hint', ['price' => '€'.(int) $timeMonthly]) }}</p>
                        @endif
                    </div>
                    @if ($showManageActions)
                        <div class="wp-billing-plan-card-action">
                            @if ($isCurrentPlan && ($billingStatus ?? null) === 'paid')
                                <span class="wp-pill wp-pill--done">{{ __('subscription.current_plan') }}</span>
                            @elseif (config("billing.plans.{$planKey}.self_activate", true))
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
                                <a
                                    href="mailto:{{ config('billing.contact_email') }}?subject={{ rawurlencode(__('subscription.contact_sales_subject')) }}"
                                    class="btn btn--ghost btn--block"
                                >
                                    {{ __('subscription.contact_sales_cta') }}
                                </a>
                            @endif
                        </div>
                    @elseif ($publicMode)
                        <div class="wp-billing-plan-card-action">
                            @if ($planKey === 'corporate')
                                <a
                                    href="mailto:{{ config('billing.contact_email') }}?subject={{ rawurlencode(__('subscription.contact_sales_subject')) }}"
                                    class="btn btn--ghost btn--block"
                                >
                                    {{ __('subscription.public_contact_cta') }}
                                </a>
                            @else
                                <a href="{{ route('register') }}" class="btn btn--primary btn--block">
                                    {{ __('subscription.public_register_cta') }}
                                </a>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if (! $publicMode && ($canRequestPurge || $purgeRequest))
        <section class="wp-card wp-card-pad wp-stack wp-billing-page-width" wire:key="subscription-purge">
            <p class="wp-section-title">{{ __('subscription.purge.title') }}</p>
            <p class="wp-muted">{{ __('subscription.purge.intro') }}</p>

            @error('purge')
                <p class="wp-error">{{ $message }}</p>
            @enderror

            @if ($purgeRequest)
                <div class="wp-stack-tight">
                    @if ($purgeRequest->status->value === 'awaiting_email')
                        <p>{{ __('subscription.purge.status_awaiting_email') }}</p>
                        <label class="wp-check">
                            <input type="checkbox" disabled>
                            <span>{{ __('subscription.purge.email_confirmed_checkbox') }}</span>
                        </label>
                    @elseif ($purgeRequest->status->value === 'ready')
                        <label class="wp-check">
                            <input type="checkbox" checked disabled>
                            <span>{{ __('subscription.purge.email_confirmed_checkbox') }}</span>
                        </label>
                        <p>{{ __('subscription.purge.status_ready') }}</p>
                        @if ($canExecuteTrialPurge)
                            <div class="wp-field wp-field--password">
                                <span class="wp-label">{{ __('subscription.purge.password_label') }}</span>
                                <x-wp-password-input
                                    wireModel="purgeExecutePassword"
                                    id="purgeExecutePassword"
                                    autocomplete="new-password"
                                    name="purge_execute_confirm"
                                />
                            </div>
                            @error('purge_password')
                                <p class="wp-error">{{ $message }}</p>
                            @enderror
                            <button
                                type="button"
                                class="btn btn--danger"
                                wire:click="preparePurgeConfirm('execute_trial')"
                            >
                                {{ __('subscription.purge.execute_trial') }}
                            </button>
                        @endif
                    @elseif ($purgeRequest->status->value === 'scheduled')
                        @if ($purgeRequest->track->value === 'expired_trial')
                            <p>{{ __('subscription.purge.status_scheduled_expired_trial', [
                                'date' => $purgeRequest->scheduled_purge_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
                                'timezone' => config('app.timezone'),
                            ]) }}</p>
                            <p class="wp-muted">{{ __('subscription.purge.expired_trial_subscribe_hint') }}</p>
                        @else
                            <p>{{ __('subscription.purge.status_scheduled', [
                                'date' => $purgeRequest->scheduled_purge_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
                                'timezone' => config('app.timezone'),
                            ]) }}</p>
                        @endif
                        <p class="wp-muted">{{ __('subscription.purge.days_remaining', [
                            'days' => $purgeRequest->daysUntilPurge() ?? 0,
                        ]) }}</p>
                        @if ($canExecutePaidPurge)
                            <button type="button" class="btn btn--danger" wire:click="preparePurgeConfirm('execute_paid')">
                                {{ __('subscription.purge.execute_paid') }}
                            </button>
                        @endif
                    @endif

                    @if ($canCancelPurge)
                        <button type="button" class="btn btn--ghost" wire:click="preparePurgeConfirm('cancel')">
                            {{ __('subscription.purge.cancel') }}
                        </button>
                    @endif
                </div>
            @elseif ($canRequestPurge)
                <div class="wp-stack-tight">
                    <p>
                        <a href="{{ route('settings.index', ['open' => 'privacy']) }}" class="wp-link">{{ __('subscription.purge.export_link') }}</a>
                    </p>
                    <label class="wp-check">
                        <input type="checkbox" wire:model.live="purgeExportAck">
                        <span>{{ __('subscription.purge.export_ack') }}</span>
                    </label>
                    @error('purge_export_ack')
                        <p class="wp-error">{{ $message }}</p>
                    @enderror

                    <div class="wp-field wp-field--password">
                        <span class="wp-label">{{ __('subscription.purge.password_label') }}</span>
                        <x-wp-password-input
                            wireModel="purgePassword"
                            id="purgePassword"
                            autocomplete="new-password"
                            name="purge_start_confirm"
                        />
                    </div>
                    @error('purge_password')
                        <p class="wp-error">{{ $message }}</p>
                    @enderror

                    @if ($purgeTrack?->value === 'trial')
                        <p class="wp-muted">{{ __('subscription.purge.trial_hint') }}</p>
                    @else
                        <p class="wp-muted">{{ __('subscription.purge.paid_hint') }}</p>
                    @endif

                    <button type="button" class="btn btn--danger" wire:click="preparePurgeConfirm('start')">
                        {{ __('subscription.purge.start') }}
                    </button>
                </div>
            @endif
        </section>
    @endif

    @if ($purgeConfirmKind)
        @php
            $purgeConfirmBody = match ($purgeConfirmKind) {
                'start' => __('subscription.purge.confirm_start'),
                'execute_trial' => __('subscription.purge.confirm_execute'),
                'execute_paid' => __('subscription.purge.confirm_execute_superuser'),
                'cancel' => __('subscription.purge.confirm_cancel'),
                default => '',
            };
            $purgeConfirmSubmit = match ($purgeConfirmKind) {
                'start' => __('subscription.purge.start'),
                'execute_trial' => __('subscription.purge.execute_trial'),
                'execute_paid' => __('subscription.purge.execute_paid'),
                'cancel' => __('subscription.purge.cancel'),
                default => __('common.button.close'),
            };
        @endphp
        <x-wp-modal closeMethod="dismissPurgeConfirm" aria-labelledby="subscription-purge-confirm-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card" role="alertdialog" aria-labelledby="subscription-purge-confirm-title">
                <div class="wp-modal-head">
                    <h2 id="subscription-purge-confirm-title" class="wp-section-title">{{ __('subscription.purge.title') }}</h2>
                    <x-wp-modal-close wire:click="dismissPurgeConfirm" />
                </div>
                <div class="wp-modal-body">
                    <p class="wp-text-body">{{ $purgeConfirmBody }}</p>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="dismissPurgeConfirm">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--danger" wire:click="confirmPurgeAction">{{ $purgeConfirmSubmit }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    <p class="wp-billing-legal wp-billing-page-width">
        <span>{{ __('subscription.legal_footer_intro') }}</span>
        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_terms') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_privacy') }}</a><span class="wp-billing-legal-sep">,</span>
        <a href="{{ route('legal.dpa') }}" target="_blank" rel="noopener noreferrer">{{ __('subscription.legal_dpa') }}</a>.
    </p>
    <p class="wp-billing-legal wp-billing-legal--hint wp-billing-page-width">{{ __('legal.inline_jurisdiction_hint') }}</p>
    @if (! $publicMode)
        <p class="wp-billing-legal wp-billing-legal--notice wp-billing-page-width">
            @if ($stripeLive)
                {{ __('subscription.stripe_notice_live') }}
            @else
                {{ __('subscription.stripe_notice_simulated') }}
            @endif
        </p>
    @endif
</div>
