<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $uiTheme ?? 'simple' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        $primaryNav = [
            ...($isPlatformOnlySuperuser ? [
                ['route' => 'platform.dashboard', 'active' => 'platform.dashboard', 'icon' => 'dashboard', 'label' => 'platform.dashboard.nav'],
                ['route' => 'platform.tenants', 'active' => 'platform.tenants', 'icon' => 'subscription', 'label' => 'platform.tenants_nav'],
                ['route' => 'platform.users', 'active' => 'platform.users', 'icon' => 'team', 'label' => 'platform.users.nav'],
                ['route' => 'platform.contact-messages', 'active' => 'platform.contact-messages', 'icon' => 'contact', 'label' => 'platform.contact_messages.nav'],
                ['route' => 'platform.audit', 'active' => 'platform.audit', 'icon' => 'document', 'label' => 'platform.audit.nav'],
                ['route' => 'platform.help', 'active' => 'platform.help', 'icon' => 'faq', 'label' => 'platform.help_nav'],
            ] : [
                ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'label' => 'common.nav.dashboard'],
                ['route' => 'locations.index', 'active' => 'locations.*', 'icon' => 'locations', 'label' => 'common.nav.locations'],
                ['route' => 'issues.index', 'active' => 'issues.*', 'icon' => 'issues', 'label' => 'common.nav.issues'],
                ['route' => 'tasks.index', 'active' => 'tasks.*', 'icon' => 'tasks', 'label' => 'common.nav.tasks'],
                ['route' => 'calendar.index', 'active' => 'calendar.*', 'icon' => 'calendar', 'label' => 'common.nav.calendar'],
            ]),
        ];

        $activeTenant = $supportTenant ?? $authUser?->tenant;
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
            ...(! $isPlatformOnlySuperuser ? [
                ['route' => 'team.index', 'active' => 'team.*', 'icon' => 'team', 'label' => 'common.nav.users'],
            ] : []),
            ...($showSettingsNav ? [
                ['route' => 'settings.index', 'active' => 'settings.index', 'icon' => 'settings', 'label' => 'common.nav.settings'],
            ] : []),
            ...($showTenantAdminNav ? [
                ['route' => 'settings.api', 'active' => 'settings.api', 'icon' => 'api', 'label' => 'settings.api.nav'],
                ['route' => 'subscription.index', 'active' => 'subscription.*', 'icon' => 'subscription', 'label' => 'common.nav.subscription'],
            ] : []),
            ['route' => 'faq.index', 'active' => 'faq.*', 'icon' => 'faq', 'label' => 'common.nav.faq'],
            ['route' => 'manual.hub', 'active' => 'manual.*', 'icon' => 'document', 'label' => 'common.nav.manual'],
            ['route' => 'legal.index', 'active' => 'legal.index', 'icon' => 'legal', 'label' => 'common.nav.legal', 'target' => '_blank'],
            ['route' => 'contact.index', 'active' => 'contact.*', 'icon' => 'contact', 'label' => 'common.nav.contact'],
        ];
    @endphp

    <div class="wp-app" x-data="{ nav: false, help: false }">
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

        @include('partials.wp-mobile-prefs')

        <button type="button" class="wp-nav-toggle-fixed btn btn--ghost btn--sm" @click="nav = !nav" aria-label="{{ __('common.nav.label') }}">
            <x-wp-icon name="menu" class="wp-icon" />
        </button>

        <a href="{{ route('dashboard') }}" class="wp-brand-float" aria-label="WinProx">
            @if (file_exists(public_path('images/Winprox_logo_100.png')))
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="" width="32" height="32" class="wp-brand-float-img">
            @else
                <img src="{{ asset('images/qr/svg/A6_winprox_logo.svg') }}" alt="" width="72" height="40" class="wp-brand-float-img">
            @endif
        </a>

        <div class="wp-content">
            @if ($supportTenant)
                <div class="wp-support-banner-bar" role="status">
                    <span>{{ __('platform.banner', ['name' => $supportTenant->name]) }}</span>
                    <a href="{{ route('platform.tenants') }}" class="btn btn--ghost btn--sm">{{ __('platform.stop') }}</a>
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
                    <h3 id="wp-help-chat-title" class="wp-help-panel-title">{{ __('help.panel_title') }}</h3>
                    <button type="button" class="wp-help-panel-close" @click="help = false" aria-label="{{ __('help.close_fab') }}">×</button>
                </div>
                <livewire:components.help-chat />
            </div>
            <button
                type="button"
                class="wp-help-button"
                @click="help = !help"
                :aria-expanded="help ? 'true' : 'false'"
                aria-controls="wp-help-chat-panel"
                :aria-label="help ? @js(__('help.close_fab')) : @js(__('help.open_fab'))"
            >
                <x-wp-icon name="chat-bubble" class="wp-icon wp-help-button-icon" x-show="!help" />
                <x-wp-icon name="x-mark" class="wp-icon wp-help-button-icon" x-show="help" x-cloak />
            </button>
        </div>
    </div>

    @livewireScripts
</body>
</html>
