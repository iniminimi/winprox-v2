<div class="wp-stack">
    <x-wp-page-head-title
        icon="dashboard"
        :title="__('platform.dashboard.title')"
        help-page="platform.dashboard"
        :subtitle="__('platform.dashboard.subtitle')"
    />

    <x-wp-translation-sync-reminder />

    @can('downloadPromoQr', auth()->user())
    <section class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('platform.promo_qr.title') }}</h2>
        </div>
        <p class="wp-muted">{{ __('platform.promo_qr.description') }}</p>
        <div class="wp-row wp-gap-sm">
            <a href="{{ route('platform.promo-qr.download') }}" class="btn btn--primary">
                {{ __('platform.promo_qr.download') }}
            </a>
            <a href="{{ route('platform.promo-recipients') }}" class="btn btn--ghost">
                {{ __('platform.promo_recipients.nav') }}
            </a>
        </div>
    </section>
    @endcan

    @php
        $kpiLinks = [
            'tenants' => route('platform.tenants'),
            'users' => route('platform.users'),
            'issues' => null,
            'tasks' => null,
            'help' => route('platform.help'),
        ];
        $kpis = [
            ['key' => 'tenants', 'icon' => 'subscription', 'label' => 'platform.dashboard.stat_tenants', 'meta' => 'platform.dashboard.stat_active_tenants', 'meta_arg' => $stats['active_tenants']],
            ['key' => 'users', 'icon' => 'team', 'label' => 'platform.dashboard.stat_users', 'meta' => null],
            ['key' => 'issues', 'icon' => 'issues', 'label' => 'platform.dashboard.stat_issues', 'meta' => null],
            ['key' => 'tasks', 'icon' => 'tasks', 'label' => 'platform.dashboard.stat_tasks', 'meta' => null],
            ['key' => 'help', 'icon' => 'faq', 'label' => 'platform.dashboard.stat_help', 'meta' => null],
        ];
    @endphp

    <div class="wp-kpis">
        @foreach ($kpis as $kpi)
            @if ($kpiLinks[$kpi['key']])
                <a href="{{ $kpiLinks[$kpi['key']] }}"
                   class="wp-kpi wp-kpi--{{ $kpi['key'] }}"
                   wire:key="kpi-{{ $kpi['key'] }}">
            @else
                <div class="wp-kpi wp-kpi--{{ $kpi['key'] }}" wire:key="kpi-{{ $kpi['key'] }}">
            @endif
                <div class="wp-kpi-body">
                    <div class="wp-kpi-main">
                        <p class="wp-kpi-kicker">{{ __($kpi['label']) }}</p>
                        <p class="wp-kpi-stats">
                            <span class="wp-kpi-value wp-tabular">{{ $stats[$kpi['key']] }}</span>
                            @if ($kpi['meta'])
                                <span class="wp-kpi-meta">{{ __($kpi['meta'], ['count' => $kpi['meta_arg']]) }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="wp-kpi-icon" aria-hidden="true">
                        <x-wp-icon :name="$kpi['icon']" />
                    </span>
                </div>
            @if ($kpiLinks[$kpi['key']])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    <div class="wp-platform-welcome-stats">
        <section class="wp-card wp-card-pad wp-platform-welcome-stats__card">
            <p class="wp-subhead">{{ __('platform.dashboard.welcome_unique_title') }}</p>
            <dl class="wp-platform-welcome-stats__list">
                <div class="wp-row">
                    <dt class="wp-muted wp-text-sm">{{ __('platform.dashboard.welcome_unique_today') }}</dt>
                    <dd class="wp-text-body wp-tabular">{{ $welcomeVisitStats->uniqueToday }}</dd>
                </div>
                <div class="wp-row">
                    <dt class="wp-muted wp-text-sm">{{ __('platform.dashboard.welcome_unique_7d') }}</dt>
                    <dd class="wp-text-body wp-tabular">{{ $welcomeVisitStats->uniqueLast7Days }}</dd>
                </div>
                <div class="wp-row">
                    <dt class="wp-muted wp-text-sm">{{ __('platform.dashboard.welcome_unique_30d') }}</dt>
                    <dd class="wp-text-body wp-tabular">{{ $welcomeVisitStats->uniqueLast30Days }}</dd>
                </div>
                <div class="wp-row">
                    <dt class="wp-muted wp-text-sm">{{ __('platform.dashboard.welcome_unique_2026') }}</dt>
                    <dd class="wp-text-body wp-tabular">{{ $welcomeVisitStats->uniqueYear2026 }}</dd>
                </div>
            </dl>
        </section>
        <section class="wp-card wp-card-pad wp-platform-welcome-stats__card">
            <p class="wp-subhead">{{ __('platform.dashboard.welcome_locale_title') }}</p>
            <dl class="wp-platform-welcome-stats__list">
                @foreach (['nl', 'fr', 'en', 'de'] as $localeCode)
                    <div class="wp-row">
                        <dt class="wp-muted wp-text-sm">{{ strtoupper($localeCode) }}</dt>
                        <dd class="wp-text-body wp-tabular">{{ $welcomeVisitStats->byLocale[$localeCode] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <div class="wp-grid wp-grid--two">
        <section class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('platform.dashboard.recent_tenants') }}</h2>
            </div>
            @if ($recentTenants->isEmpty())
                <p class="wp-muted">{{ __('platform.dashboard.empty') }}</p>
            @else
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($recentTenants as $tenant)
                        <div class="wp-list-row" wire:key="tenant-{{ $tenant->id }}">
                            <div class="wp-grow">
                                <p class="wp-text-body"><strong>{{ $tenant->name }}</strong></p>
                                <p class="wp-muted wp-text-sm">#{{ $tenant->id }} · {{ $tenant->created_at?->format('d-m-Y H:i') }}</p>
                            </div>
                            <span class="wp-pill {{ $tenant->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                {{ $tenant->is_active ? __('platform.status_active') : __('platform.status_inactive') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('platform.dashboard.recent_users') }}</h2>
            </div>
            @if ($recentUsers->isEmpty())
                <p class="wp-muted">{{ __('platform.dashboard.empty') }}</p>
            @else
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($recentUsers as $user)
                        <div class="wp-list-row" wire:key="user-{{ $user->id }}">
                            <div class="wp-grow">
                                <p class="wp-text-body">
                                    <strong>{{ $user->name }}</strong>
                                    @if ($user->is_superuser)
                                        <span class="wp-pill wp-pill--progress">{{ __('platform.users.superuser') }}</span>
                                    @elseif (! $user->is_active)
                                        <span class="wp-pill wp-pill--closed">{{ __('platform.users.inactive') }}</span>
                                    @endif
                                </p>
                                <p class="wp-muted wp-text-sm">{{ $user->email }} · {{ $user->tenant?->name ?? __('platform.users.no_tenant') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('platform.dashboard.recent_audit') }}</h2>
            <a href="{{ route('platform.audit') }}" class="btn btn--ghost btn--sm">{{ __('platform.dashboard.open_audit') }}</a>
        </div>
        @if ($recentAuditLogs === [])
            <p class="wp-muted">{{ __('platform.dashboard.empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($recentAuditLogs as $log)
                    <div class="wp-list-row" wire:key="audit-{{ $log['id'] }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $log['title'] }}</strong>
                                <span class="wp-muted wp-text-sm"> · {{ $log['meta'] }}</span>
                            </p>
                            @if (! empty($log['context']))
                                <p class="wp-muted wp-text-sm">{{ $log['context'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
