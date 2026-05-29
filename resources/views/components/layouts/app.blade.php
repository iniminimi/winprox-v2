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
    <header class="wp-topbar">
        <div class="wp-topbar-inner">
            <a href="{{ route('dashboard') }}" class="wp-brand">WinProx</a>
            <div class="wp-topbar-meta">
                @auth
                    <span class="wp-tenant">{{ auth()->user()->tenant?->name ?? __('common.app.platform') }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">{{ __('common.button.logout') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <div class="wp-body">
        <nav class="wp-nav" aria-label="{{ __('common.nav.label') }}">
            <a href="{{ route('dashboard') }}"
               class="wp-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                {{ __('common.nav.dashboard') }}
            </a>
            <a href="{{ route('issues.index') }}"
               class="wp-nav-link {{ request()->routeIs('issues.*') ? 'is-active' : '' }}">
                {{ __('common.nav.issues') }}
            </a>
        </nav>

        <main class="wp-main">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
