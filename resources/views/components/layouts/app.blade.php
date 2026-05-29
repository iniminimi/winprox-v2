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
        $primaryNav = [
            ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'label' => 'common.nav.dashboard'],
            ['route' => 'locations.index', 'active' => 'locations.*', 'icon' => 'locations', 'label' => 'common.nav.locations'],
            ['route' => 'issues.index', 'active' => 'issues.*', 'icon' => 'issues', 'label' => 'common.nav.issues'],
            ['route' => 'tasks.index', 'active' => 'tasks.*', 'icon' => 'tasks', 'label' => 'common.nav.tasks'],
            ['route' => 'calendar.index', 'active' => 'calendar.*', 'icon' => 'calendar', 'label' => 'common.nav.calendar'],
        ];
        $secondaryNav = [
            ['route' => 'team.index', 'active' => 'team.*', 'icon' => 'team', 'label' => 'common.nav.team'],
            ...(auth()->user()?->isAdmin() ? [['route' => 'settings.api', 'active' => 'settings.*', 'icon' => 'subscription', 'label' => 'settings.api.nav']] : []),
            ['route' => 'subscription.index', 'active' => 'subscription.*', 'icon' => 'subscription', 'label' => 'common.nav.subscription'],
            ['route' => 'faq.index', 'active' => 'faq.*', 'icon' => 'faq', 'label' => 'common.nav.faq'],
            ['route' => 'legal.index', 'active' => 'legal.*', 'icon' => 'legal', 'label' => 'common.nav.legal'],
            ['route' => 'contact.index', 'active' => 'contact.*', 'icon' => 'contact', 'label' => 'common.nav.contact'],
        ];
    @endphp

    <div class="wp-app" x-data="{ nav: false, help: false }">
        <aside class="wp-sidebar" :class="{ 'is-open': nav }">
            <div class="wp-sidebar-head">
                <span class="wp-sidebar-tenant-label">{{ __('common.app.workspace') }}</span>
                <span class="wp-sidebar-tenant">{{ auth()->user()->tenant?->name ?? __('common.app.platform') }}</span>
            </div>

            <nav class="wp-sidebar-nav" aria-label="{{ __('common.nav.label') }}">
                <div class="wp-nav-group">
                    @foreach ($primaryNav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="wp-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
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
                           @click="nav = false">
                            <x-wp-icon :name="$item['icon']" class="wp-nav-icon" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>

            <div class="wp-sidebar-foot">
                @include('partials.wp-lang-switch', ['variant' => 'sidebar'])
            </div>
        </aside>

        <div class="wp-sidebar-scrim" :class="{ 'is-open': nav }" @click="nav = false" aria-hidden="true"></div>

        <div class="wp-content">
            <header class="wp-content-top">
                <button type="button" class="wp-nav-toggle btn btn--ghost btn--sm" @click="nav = !nav" aria-label="{{ __('common.nav.label') }}">
                    <x-wp-icon name="dashboard" class="wp-icon" />
                </button>

                <div class="wp-content-top-right">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <x-wp-icon name="logout" class="wp-icon" />
                                <span>{{ __('common.button.logout') }}</span>
                            </button>
                        </form>
                    @endauth
                    <a href="{{ route('dashboard') }}" class="wp-brand">WinProx</a>
                </div>
            </header>

            <main class="wp-main">
                {{ $slot }}
            </main>
        </div>

        {{-- Zwevende hulpknop (rechtsonder) — toggelt een klein paneel. --}}
        <div class="wp-help">
            <div class="wp-help-panel" x-show="help" x-cloak x-transition>
                <h3 class="wp-help-title">{{ __('common.help.title') }}</h3>
                <p class="wp-help-text">{{ __('common.help.text') }}</p>
                <a href="{{ route('contact.index') }}" class="btn btn--primary btn--sm btn--block">{{ __('common.help.contact') }}</a>
            </div>
            <button type="button" class="wp-help-button" @click="help = !help" aria-label="{{ __('common.help.button') }}">
                <x-wp-icon name="help" class="wp-icon" />
            </button>
        </div>
    </div>

    @livewireScripts
</body>
</html>
