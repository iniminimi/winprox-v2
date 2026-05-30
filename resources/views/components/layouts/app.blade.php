<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WinProx' }}</title>
    @vite(['resources/css/app.css'])
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

        $primaryNav = [
            ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'label' => 'common.nav.dashboard'],
            ['route' => 'locations.index', 'active' => 'locations.*', 'icon' => 'locations', 'label' => 'common.nav.locations'],
            ['route' => 'issues.index', 'active' => 'issues.*', 'icon' => 'issues', 'label' => 'common.nav.issues'],
            ['route' => 'tasks.index', 'active' => 'tasks.*', 'icon' => 'tasks', 'label' => 'common.nav.tasks'],
            ['route' => 'calendar.index', 'active' => 'calendar.*', 'icon' => 'calendar', 'label' => 'common.nav.calendar'],
        ];
        $secondaryNav = [
            ...(auth()->user()?->is_superuser ? [
                ['route' => 'platform.tenants', 'active' => 'platform.tenants', 'icon' => 'subscription', 'label' => 'platform.nav'],
                ['route' => 'platform.help', 'active' => 'platform.help', 'icon' => 'faq', 'label' => 'platform.help_nav'],
            ] : []),
            ['route' => 'team.index', 'active' => 'team.*', 'icon' => 'team', 'label' => 'common.nav.team'],
            ...(auth()->user()?->isAdmin() ? [
                ['route' => 'settings.api', 'active' => 'settings.*', 'icon' => 'subscription', 'label' => 'settings.api.nav'],
                ['route' => 'subscription.index', 'active' => 'subscription.*', 'icon' => 'subscription', 'label' => 'common.nav.subscription'],
            ] : []),
            ['route' => 'faq.index', 'active' => 'faq.*', 'icon' => 'faq', 'label' => 'common.nav.faq'],
            ['route' => 'legal.index', 'active' => 'legal.index', 'icon' => 'legal', 'label' => 'common.nav.legal', 'target' => '_blank'],
            ['route' => 'contact.index', 'active' => 'contact.*', 'icon' => 'contact', 'label' => 'common.nav.contact'],
        ];
    @endphp

    <div class="wp-app" x-data="{ nav: false, help: false }">
        <aside class="wp-sidebar" :class="{ 'is-open': nav }">
            <div class="wp-sidebar-head">
                <span class="wp-sidebar-brand">WinProx</span>
                @if ($supportTenant)
                    <span class="wp-sidebar-tenant">{{ $supportTenant->name }}</span>
                @elseif (auth()->user()?->tenant?->name)
                    <span class="wp-sidebar-tenant">{{ auth()->user()->tenant->name }}</span>
                @endif
            </div>

            <nav class="wp-sidebar-nav" aria-label="{{ __('common.nav.label') }}">
                <div class="wp-nav-group">
                    @foreach ($primaryNav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="wp-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                           @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif
                           @click="nav = false">
                            <x-wp-icon :name="$item['icon']" class="wp-nav-icon" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="wp-nav-group wp-nav-group--secondary">
                    @foreach ($secondaryNav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="wp-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                           @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif
                           @click="nav = false">
                            <x-wp-icon :name="$item['icon']" class="wp-nav-icon" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>

            <div class="wp-sidebar-foot">
                @include('partials.wp-lang-switch', ['variant' => 'sidebar'])

                @auth
                    <div class="wp-sidebar-user">
                        <p class="wp-sidebar-user-name">{{ auth()->user()->name }}</p>
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
        </aside>

        <div class="wp-sidebar-scrim" :class="{ 'is-open': nav }" @click="nav = false" aria-hidden="true"></div>

        <button type="button" class="wp-nav-toggle-fixed btn btn--ghost btn--sm" @click="nav = !nav" aria-label="{{ __('common.nav.label') }}">
            <x-wp-icon name="menu" class="wp-icon" />
        </button>

        <a href="{{ route('dashboard') }}" class="wp-brand-float" aria-label="WinProx">
            <img src="{{ asset('images/qr/svg/A6_winprox_logo.svg') }}" alt="" width="72" height="40" class="wp-brand-float-img">
        </a>

        <div class="wp-content">
            @if ($supportTenant)
                <div class="wp-support-banner-bar" role="status">
                    <span>{{ __('platform.banner', ['name' => $supportTenant->name]) }}</span>
                    <a href="{{ route('platform.tenants') }}" class="btn btn--ghost btn--sm">{{ __('platform.stop') }}</a>
                </div>
            @endif
            <main class="wp-main">
                {{ $slot }}
            </main>
        </div>

        <div class="wp-help">
            <div class="wp-help-panel" x-show="help" x-cloak x-transition>
                <h3 class="wp-help-title">{{ __('help.panel_title') }}</h3>
                <livewire:components.help-chat />
            </div>
            <button type="button" class="wp-help-button" @click="help = !help" aria-label="{{ __('common.help.button') }}">
                <x-wp-icon name="help" class="wp-icon" />
            </button>
        </div>
    </div>

    @livewireScripts
</body>
</html>
