<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no" data-theme="{{ $uiTheme ?? 'simple' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="wp-date-locale" content="{{ \App\Support\Translation\LocaleSupport::dateInputLang() }}">
    <title>{{ $title ?? 'WinProx' }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="wp-shell">
    @php
        use App\Models\Tenant;
        use App\Support\Platform\SupportTenantContext;

        $supportTenant = null;
        if (auth()->user()?->is_superuser && SupportTenantContext::isActive()) {
            $supportTenant = Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        $authUser = auth()->user();
        $isPlatformOnlySuperuser = $authUser?->is_superuser && $supportTenant === null;

        $activeTenant = $supportTenant ?? $authUser?->tenant;
        $showEsgNav = $activeTenant instanceof Tenant && $activeTenant->hasEsgModule();
        $showIotNav = $activeTenant instanceof Tenant && $activeTenant->hasIotModule();
        $showTimeNav = $activeTenant instanceof Tenant && $activeTenant->hasTimeModule();

        $primaryNav = [
            ...($isPlatformOnlySuperuser ? [
                ['route' => 'platform.dashboard', 'active' => 'platform.dashboard', 'icon' => 'dashboard', 'label' => 'platform.dashboard.nav'],
                ['route' => 'platform.tenants', 'active' => 'platform.tenants', 'icon' => 'subscription', 'label' => 'platform.tenants_nav'],
                ['route' => 'platform.users', 'active' => 'platform.users', 'icon' => 'team', 'label' => 'platform.users.nav'],
                ['route' => 'platform.email-unsubscribes', 'active' => 'platform.email-unsubscribes', 'icon' => 'contact', 'label' => 'platform.email_unsubscribe.nav'],
                ['route' => 'platform.audit', 'active' => 'platform.audit', 'icon' => 'document', 'label' => 'platform.audit.nav'],
                ['route' => 'platform.help', 'active' => 'platform.help', 'icon' => 'faq', 'label' => 'platform.help_nav'],
                ['route' => 'platform.screenshots', 'active' => 'platform.screenshots', 'icon' => 'document', 'label' => 'platform.manual_screenshots.nav'],
                ['route' => 'platform.promo-recipients', 'active' => 'platform.promo-recipients', 'icon' => 'document', 'label' => 'platform.promo_recipients.nav'],
                ['route' => 'platform.promo-campaigns', 'active' => 'platform.promo-campaigns*', 'icon' => 'document', 'label' => 'platform.promo_campaigns.nav'],
                ['route' => 'platform.translations', 'active' => 'platform.translations', 'icon' => 'issues', 'label' => 'platform.translation_sync.nav'],
            ] : [
                ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'label' => 'common.nav.dashboard'],
                // Overige navigatie wordt in accordion-groepen gerenderd.
            ]),
        ];

        $showTenantAdminNav = $authUser && (
            ($activeTenant instanceof Tenant && $authUser->can('manageSubscription', $activeTenant))
            || ($authUser->is_superuser && $supportTenant !== null)
        );

        $showSettingsNav = $authUser && (
            $authUser->tenant_id !== null
            || ($authUser->is_superuser && $supportTenant !== null)
        );

        $secondaryNav = [
            ...($authUser?->is_superuser && $supportTenant !== null ? [
                ['route' => 'platform.dashboard', 'active' => 'platform.*', 'icon' => 'subscription', 'label' => 'platform.back_nav'],
            ] : []),
            ...($showSettingsNav ? [
                ['route' => 'settings.index', 'active' => 'settings.index', 'icon' => 'settings', 'label' => 'common.nav.settings'],
            ] : []),
            ...($showTenantAdminNav && ($activeTenant?->hasApiAccess() ?? false) ? [
                ['route' => 'settings.api', 'active' => 'settings.api', 'icon' => 'api', 'label' => 'settings.api.nav'],
            ] : []),
            ...($showTenantAdminNav ? [
                ['route' => 'subscription.index', 'active' => 'subscription.*', 'icon' => 'subscription', 'label' => 'common.nav.subscription'],
            ] : []),
            ['route' => 'faq.index', 'active' => 'faq.*', 'icon' => 'faq', 'label' => 'common.nav.faq'],
            ['route' => 'manual.hub', 'active' => 'manual.*', 'icon' => 'document', 'label' => 'common.nav.manual'],
            ['route' => 'legal.index', 'active' => 'legal.index', 'icon' => 'legal', 'label' => 'common.nav.legal', 'target' => '_blank'],
            ['route' => 'contact.index', 'active' => 'contact.*', 'icon' => 'contact', 'label' => 'common.nav.contact'],
        ];
    @endphp

    <div
        class="wp-app"
        x-data="{
            nav: false,
            help: false,
            helpFabShowQuestion: false,
            helpVideoUrl: @js(asset('video/assistant_small.mp4')),
            helpVideoReady: false,
            init() {
                try {
                    const key = 'wp_help_fab_page_count';
                    const next = (parseInt(sessionStorage.getItem(key) || '0', 10) || 0) + 1;
                    sessionStorage.setItem(key, String(next));
                    this.helpFabShowQuestion = next % 3 === 0;
                } catch (e) {
                    this.helpFabShowQuestion = false;
                }
            },
            preloadHelpVideo() {
                if (this.helpVideoReady) {
                    return Promise.resolve();
                }
                return new Promise((resolve) => {
                    const video = document.createElement('video');
                    video.preload = 'auto';
                    video.muted = true;
                    video.playsInline = true;
                    const done = () => {
                        this.helpVideoReady = true;
                        resolve();
                    };
                    video.oncanplaythrough = done;
                    video.onerror = done;
                    video.src = this.helpVideoUrl;
                    video.load();
                });
            },
            restartHelpVideo() {
                this.$nextTick(() => {
                    const video = this.$refs.helpVideo;
                    if (! video) {
                        return;
                    }
                    video.currentTime = 0;
                    video.play().catch(() => {});
                });
            },
            async toggleHelp() {
                if (this.help) {
                    this.help = false;
                    return;
                }
                await this.preloadHelpVideo();
                this.help = true;
                this.restartHelpVideo();
            },
        }"
    >
        <aside class="wp-sidebar" :class="{ 'is-open': nav }"
                @php
                    $tenant = auth()->user()?->tenant;
                    $tenantBg = $tenant?->custom_theme_active && $tenant?->custom_theme_bg ? $tenant->custom_theme_bg : null;
                    $tenantLogo = $tenant?->logoPublicUrl();
                    $fallbackLogo = asset('images/Winprox_logo_100.png');
                @endphp
                @if($tenantBg) style="--wp-tenant-bg: {{ $tenantBg }};" @endif>
            <div class="wp-sidebar-header">
                <div class="wp-sidebar-header-logo">
                    <img src="{{ $tenantLogo ?? $fallbackLogo }}" alt="{{ $tenant?->name ?? 'WinProx' }}">
                </div>
            </div>
            <div class="wp-sidebar-body">
                <nav class="wp-sidebar-menu" aria-label="{{ __('common.nav.label') }}">
                    @foreach ($primaryNav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="wp-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                           @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif
                           @click="nav = false">
                            <x-wp-icon :name="$item['icon']" class="wp-nav-icon" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach

                    @if ($isPlatformOnlySuperuser)
                        <hr class="wp-nav-divider" role="presentation" aria-hidden="true">

                        @foreach ($secondaryNav as $item)
                            <a href="{{ route($item['route']) }}"
                               class="wp-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                               @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif
                               @click="nav = false">
                                <x-wp-icon :name="$item['icon']" class="wp-nav-icon" />
                                <span>{{ __($item['label']) }}</span>
                            </a>
                        @endforeach
                    @else
                        @php
                            $inspectionRoundOnlyActive = request()->routeIs('issues.index') && (int) request()->query('inspection_round', 0) === 1;
                            $meldingenActive = request()->routeIs('issues.*') && ! $inspectionRoundOnlyActive;

                            $workGroupActive = request()->routeIs('issues.*')
                                || request()->routeIs('tasks.*')
                                || request()->routeIs('calendar.*')
                                || request()->routeIs('reservations.*');

                            $categoriesActive = request()->routeIs('locations.index')
                                && (request()->query('section') === 'categories' || request()->filled('edit_category'));
                            $locationsActive = request()->routeIs('locations.index') && ! $categoriesActive;
                            $unitsActive = request()->routeIs('units.index');
                            $placesGroupActive = request()->routeIs('locations.*') || $unitsActive;

                            $peopleGroupActive = request()->routeIs('team.index');
                            $peopleSection = $peopleGroupActive ? (string) request()->query('section', '') : '';
                            $backofficeNavActive = $peopleGroupActive && $peopleSection === 'backoffice';
                            $teamsNavActive = $peopleGroupActive && $peopleSection !== 'backoffice';

                            $timeGroupActive = request()->routeIs('time.*');
                            $automationGroupActive = request()->routeIs('iot.*') || request()->routeIs('esg.*');
                            $organizationGroupActive = request()->routeIs('settings.*') || request()->routeIs('subscription.*');
                            $helpGroupActive = request()->routeIs('faq.*')
                                || request()->routeIs('manual.*')
                                || request()->routeIs('legal.index')
                                || request()->routeIs('contact.*');
                        @endphp

                        <hr class="wp-nav-divider" role="presentation" aria-hidden="true">

                        <div
                            class="wp-sidebar-accordion"
                            x-data="{
                                exclusive(event) {
                                    const opened = event.target;
                                    if (! (opened instanceof HTMLDetailsElement) || ! opened.open) {
                                        return;
                                    }
                                    this.$el.querySelectorAll('details.wp-sidebar-accordion__group').forEach((el) => {
                                        if (el !== opened) {
                                            el.open = false;
                                        }
                                    });
                                },
                            }"
                            @toggle.capture="exclusive($event)"
                        >
                            <details class="wp-sidebar-accordion__group" @if($workGroupActive) open @endif>
                                <summary class="wp-nav-link {{ $workGroupActive ? 'is-active' : '' }}">
                                    <x-wp-icon name="issues" class="wp-nav-icon" />
                                    <span>{{ __('common.nav.work') }}</span>
                                </summary>
                                <div class="wp-sidebar-accordion__panel">
                                    <a href="{{ route('issues.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $meldingenActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.issues') }}</span>
                                    </a>
                                    <a href="{{ route('issues.index', ['recurring' => 1, 'inspection_round' => 1]) }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $inspectionRoundOnlyActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('issues.list.inspection_rounds') }}</span>
                                    </a>
                                    <a href="{{ route('tasks.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('tasks.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.tasks') }}</span>
                                    </a>
                                    <a href="{{ route('calendar.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('calendar.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.calendar') }}</span>
                                    </a>
                                    <a href="{{ route('reservations.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('reservations.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.reservations') }}</span>
                                    </a>
                                </div>
                            </details>

                            <details class="wp-sidebar-accordion__group" @if($placesGroupActive) open @endif>
                                <summary class="wp-nav-link {{ $placesGroupActive ? 'is-active' : '' }}">
                                    <x-wp-icon name="locations" class="wp-nav-icon" />
                                    <span>{{ __('common.nav.places') }}</span>
                                </summary>
                                <div class="wp-sidebar-accordion__panel">
                                    <a href="{{ route('locations.index', ['section' => 'categories']) }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $categoriesActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('locations.categories.title') }}</span>
                                    </a>
                                    <a href="{{ route('locations.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $locationsActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('locations.title') }}</span>
                                    </a>
                                    <a href="{{ route('units.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $unitsActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('units.title') }}</span>
                                    </a>
                                </div>
                            </details>

                            <details class="wp-sidebar-accordion__group" @if($peopleGroupActive) open @endif>
                                <summary class="wp-nav-link {{ $peopleGroupActive ? 'is-active' : '' }}">
                                    <x-wp-icon name="team" class="wp-nav-icon" />
                                    <span>{{ __('common.nav.people') }}</span>
                                </summary>
                                <div class="wp-sidebar-accordion__panel">
                                    @can('create', App\Models\User::class)
                                        <a href="{{ route('team.index', ['section' => 'backoffice']) }}"
                                           class="wp-nav-link wp-nav-link--sub {{ $backofficeNavActive ? 'is-active' : '' }}"
                                           @click="nav = false">
                                            <span>{{ __('common.nav.backoffice') }}</span>
                                        </a>
                                    @endcan
                                    <a href="{{ route('team.index', ['section' => 'teams']) }}"
                                       class="wp-nav-link wp-nav-link--sub {{ $teamsNavActive ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('team.nav.teams') }}</span>
                                    </a>
                                </div>
                            </details>

                            @if ($showTimeNav)
                                <details class="wp-sidebar-accordion__group" @if($timeGroupActive) open @endif>
                                    <summary class="wp-nav-link {{ $timeGroupActive ? 'is-active' : '' }}">
                                        <x-wp-icon name="clock" class="wp-nav-icon" />
                                        <span>{{ __('common.nav.time') }}</span>
                                    </summary>
                                    <div class="wp-sidebar-accordion__panel">
                                        <a href="{{ route('time.clock-points.index') }}"
                                           class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('time.clock-points.index') ? 'is-active' : '' }}"
                                           @click="nav = false">
                                            <span>{{ __('time.nav.clock_points') }}</span>
                                        </a>
                                        <a href="{{ route('time.presence.index') }}"
                                           class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('time.presence.index') ? 'is-active' : '' }}"
                                           @click="nav = false">
                                            <span>{{ __('time.nav.presence') }}</span>
                                        </a>
                                    </div>
                                </details>
                            @endif

                            @if ($showEsgNav || $showIotNav)
                                <details class="wp-sidebar-accordion__group" @if($automationGroupActive) open @endif>
                                    <summary class="wp-nav-link {{ $automationGroupActive ? 'is-active' : '' }}">
                                        <x-wp-icon name="sliders" class="wp-nav-icon" />
                                        <span>{{ __('common.nav.automation') }}</span>
                                    </summary>
                                    <div class="wp-sidebar-accordion__panel">
                                        @if ($showIotNav)
                                            <a href="{{ route('iot.index') }}"
                                               class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('iot.*') ? 'is-active' : '' }}"
                                               @click="nav = false">
                                                <span>{{ __('common.nav.iot') }}</span>
                                            </a>
                                        @endif
                                        @if ($showEsgNav)
                                            <a href="{{ route('esg.dashboard') }}"
                                               class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('esg.*') ? 'is-active' : '' }}"
                                               @click="nav = false">
                                                <span>{{ __('common.nav.esg') }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </details>
                            @endif

                            @if ($showSettingsNav || ($showTenantAdminNav && ($activeTenant?->hasApiAccess() ?? false)) || $showTenantAdminNav)
                                <details class="wp-sidebar-accordion__group" @if($organizationGroupActive) open @endif>
                                    <summary class="wp-nav-link {{ $organizationGroupActive ? 'is-active' : '' }}">
                                        <x-wp-icon name="settings" class="wp-nav-icon" />
                                        <span>{{ __('common.nav.organization') }}</span>
                                    </summary>
                                    <div class="wp-sidebar-accordion__panel">
                                        @if ($showSettingsNav)
                                            <a href="{{ route('settings.index') }}"
                                               class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('settings.index') ? 'is-active' : '' }}"
                                               @click="nav = false">
                                                <span>{{ __('common.nav.settings') }}</span>
                                            </a>
                                        @endif
                                        @if ($showTenantAdminNav && ($activeTenant?->hasApiAccess() ?? false))
                                            <a href="{{ route('settings.api') }}"
                                               class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('settings.api') ? 'is-active' : '' }}"
                                               @click="nav = false">
                                                <span>{{ __('settings.api.nav') }}</span>
                                            </a>
                                        @endif
                                        @if ($showTenantAdminNav)
                                            <a href="{{ route('subscription.index') }}"
                                               class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('subscription.*') ? 'is-active' : '' }}"
                                               @click="nav = false">
                                                <span>{{ __('common.nav.subscription') }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </details>
                            @endif

                            <details class="wp-sidebar-accordion__group" @if($helpGroupActive) open @endif>
                                <summary class="wp-nav-link {{ $helpGroupActive ? 'is-active' : '' }}">
                                    <x-wp-icon name="faq" class="wp-nav-icon" />
                                    <span>{{ __('common.nav.help') }}</span>
                                </summary>
                                <div class="wp-sidebar-accordion__panel">
                                    <a href="{{ route('faq.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('faq.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.faq') }}</span>
                                    </a>
                                    <a href="{{ route('manual.hub') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('manual.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.manual') }}</span>
                                    </a>
                                    <a href="{{ route('legal.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('legal.index') ? 'is-active' : '' }}"
                                       target="_blank" rel="noopener noreferrer"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.legal') }}</span>
                                    </a>
                                    <a href="{{ route('contact.index') }}"
                                       class="wp-nav-link wp-nav-link--sub {{ request()->routeIs('contact.*') ? 'is-active' : '' }}"
                                       @click="nav = false">
                                        <span>{{ __('common.nav.contact') }}</span>
                                    </a>
                                </div>
                            </details>
                        </div>
                    @endif
                </nav>

                <div class="wp-sidebar-bottom">
                    @include('partials.wp-lang-switch', ['variant' => 'sidebar'])

                    @auth
                        <div class="wp-sidebar-user">
                            <p class="wp-sidebar-user-name">{{ __('common.welcome') }} {{ auth()->user()->name }}</p>
                            @if ($supportTenant)
                                <p class="wp-sidebar-user-meta">{{ $supportTenant->name }} ({{ __('platform.nav') }})</p>
                            @elseif (auth()->user()->tenant?->name)
                                <p class="wp-sidebar-user-meta">{{ auth()->user()->tenant->name }}</p>
                            @endif
                            <p class="wp-sidebar-user-meta">{{ auth()->user()->email }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="wp-sidebar-logout-form">
                                @csrf
                                <button type="submit" class="wp-sidebar-logout">{{ __('common.button.logout') }}</button>
                            </form>
                        </div>
                    @endauth
                </div>
            </div>
        </aside>

        <div class="wp-sidebar-scrim" :class="{ 'is-open': nav }" @click="nav = false" aria-hidden="true"></div>

        <button type="button" class="wp-nav-toggle-fixed btn btn--ghost btn--sm" @click="nav = !nav" aria-label="{{ __('common.nav.label') }}">
            <x-wp-icon name="menu" class="wp-icon" />
        </button>

        <a href="{{ route('dashboard') }}" class="wp-brand-float" aria-label="WinProx">
            @if (file_exists(public_path('images/Winprox_logo_100.png')))
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="" width="32" height="32" class="wp-brand-float-img">
            @else
                <img src="{{ asset('images/Winprox_logo_200.png') }}" alt="" width="72" height="40" class="wp-brand-float-img">
            @endif
        </a>

        <div class="wp-content">
            @if ($supportTenant)
                <div class="wp-support-banner-bar" role="status">
                    <span>{{ __('platform.banner', ['name' => $supportTenant->name]) }}</span>
                    <a href="{{ route('platform.tenants') }}" class="btn btn--ghost btn--sm">{{ __('platform.stop') }}</a>
                </div>
            @endif

            @php
                $purgeBannerRequest = null;
                $purgeBannerTenant = $activeTenant;
                if ($purgeBannerTenant instanceof Tenant) {
                    $purgeBannerRequest = \App\Models\TenantPurgeRequest::query()
                        ->where('tenant_id', $purgeBannerTenant->id)
                        ->where('status', \App\Enums\TenantPurgeStatus::Scheduled->value)
                        ->whereNotNull('scheduled_purge_at')
                        ->latest('id')
                        ->first();
                }
            @endphp
            @if ($purgeBannerRequest)
                <div class="wp-purge-banner-bar" role="status">
                    @can('cancelTenantPurge', $purgeBannerTenant)
                        <a href="{{ route('subscription.index') }}" class="btn btn--ghost btn--sm">{{ __('subscription.purge.banner_link') }}</a>
                    @endcan
                    <span>{{ __(
                        $purgeBannerRequest->track === \App\Enums\TenantPurgeTrack::ExpiredTrial
                            ? 'subscription.purge.banner_expired_trial'
                            : 'subscription.purge.banner',
                        [
                            'days' => $purgeBannerRequest->daysUntilPurge() ?? 0,
                            'date' => $purgeBannerRequest->scheduled_purge_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                        ]
                    ) }}</span>
                </div>
            @endif

            <div class="wp-header-search">
                <livewire:global-search />
            </div>

            <main class="wp-main">
                {{ $slot }}
            </main>
        </div>

        <div class="wp-help">
            <div class="wp-help-panel" x-show="help" x-cloak x-transition id="wp-help-chat-panel" role="dialog" aria-modal="true" aria-labelledby="wp-help-chat-title">
                <div class="wp-help-panel-header">
                    <div class="wp-help-panel-headline">
                        <div class="wp-help-avatar wp-help-avatar--header">
                            <video
                                x-ref="helpVideo"
                                class="wp-help-avatar__video"
                                src="{{ asset('video/assistant_small.mp4') }}"
                                width="80"
                                height="80"
                                muted
                                loop
                                playsinline
                                preload="auto"
                                aria-label="{{ __('help.avatar_alt') }}"
                            ></video>
                        </div>
                        <div class="wp-help-panel-title-wrap">
                            <h3 id="wp-help-chat-title" class="wp-help-panel-title">{{ __('help.panel_title') }}</h3>
                            <p class="wp-help-panel-subtitle">{{ __('help.welcome') }}</p>
                        </div>
                    </div>
                    <button type="button" class="wp-help-panel-close" @click="help = false" aria-label="{{ __('help.close_fab') }}">&times;</button>
                </div>
                <div class="wp-help-panel-body">
                    <div class="wp-help-panel-chat">
                        <livewire:components.help-chat />
                    </div>
                </div>
            </div>
            <button
                type="button"
                class="wp-help-button"
                @click="toggleHelp()"
                @mouseenter="preloadHelpVideo()"
                @focus="preloadHelpVideo()"
                :aria-expanded="help ? 'true' : 'false'"
                aria-controls="wp-help-chat-panel"
                :aria-label="help ? @js(__('help.close_fab')) : @js(__('help.open_fab'))"
            >
                <span class="wp-help-button-avatar" x-show="!help" aria-hidden="true">
                    <img
                        class="wp-help-button-avatar__img"
                        src="{{ asset('images/assistant_small.jpg') }}"
                        alt=""
                        width="80"
                        height="80"
                        decoding="async"
                        x-show="!helpFabShowQuestion"
                    >
                    <video
                        class="wp-help-button-avatar__video"
                        src="{{ asset('video/assistant_question.mp4') }}"
                        width="80"
                        height="80"
                        muted
                        playsinline
                        preload="auto"
                        x-show="helpFabShowQuestion"
                        x-cloak
                        x-init="
                            setTimeout(() => {
                                $el.currentTime = 0;
                                $el.play().catch(() => {});
                            }, 1000);
                        "
                    ></video>
                </span>
                <x-wp-icon name="x-mark" class="wp-icon wp-help-button-icon" x-show="help" x-cloak />
            </button>
        </div>
    </div>

    @livewireScripts
</body>
</html>






