@php
    $onWelcome = request()->routeIs('welcome');
    $welcomeSection = static fn (string $id): string => $onWelcome ? "#{$id}" : route('welcome')."#{$id}";
@endphp

<nav class="wp-welcome-nav" aria-label="{{ __('welcome.meta_title') }}">
    <div class="wp-welcome-nav-inner">
        @include('partials.wp-welcome-brand')
        <details class="wp-welcome-nav-menu">
            <summary class="wp-welcome-nav-menu__toggle btn btn--ghost btn--sm" aria-label="{{ __('welcome.nav.menu') }}">
                <x-wp-icon name="menu" class="wp-icon" aria-hidden="true" />
                <span class="wp-welcome-nav-menu__toggle-label">{{ __('welcome.nav.menu') }}</span>
            </summary>
            <div class="wp-welcome-nav-menu__panel">
                <div class="wp-welcome-nav-links">
                    <a href="{{ $welcomeSection('producten') }}">{{ __('welcome.nav.products') }}</a>
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>{{ __('welcome.nav.about') }}</a>
                    <a href="{{ $welcomeSection('flow') }}">{{ __('welcome.nav.how') }}</a>
                    <a href="{{ $welcomeSection('qr') }}">{{ __('welcome.nav.qr') }}</a>
                    <a href="{{ $welcomeSection('platform') }}">{{ __('welcome.nav.platform') }}</a>
                    <a href="{{ $welcomeSection('esg') }}">{{ __('welcome.nav.esg') }}</a>
                    <a href="{{ route('features.iot') }}" @if (request()->routeIs('features.iot')) aria-current="page" @endif>{{ __('welcome.nav.iot') }}</a>
                    <a href="{{ $welcomeSection('organisaties') }}">{{ __('welcome.nav.sectors') }}</a>
                    <a href="{{ $welcomeSection('video') }}">{{ __('welcome.nav.video') }}</a>
                    <a href="{{ route('faq.public') }}" @if (request()->routeIs('faq.public')) aria-current="page" @endif>{{ __('welcome.nav.faq') }}</a>
                    <a href="{{ route('api.public') }}" @if (request()->routeIs('api.public')) aria-current="page" @endif>{{ __('welcome.nav.api') }}</a>
                    <a href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>
                </div>
                <div class="wp-welcome-nav-menu__auth wp-cluster">
                    <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.hero.cta_start') }}</a>
                </div>
            </div>
        </details>
        <div class="wp-welcome-nav-actions">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
            <a href="{{ route('login') }}" class="btn btn--ghost btn--sm wp-welcome-nav-actions__auth">{{ __('welcome.login') }}</a>
            <a href="{{ route('register') }}" class="btn btn--primary btn--sm wp-welcome-nav-actions__auth">{{ __('welcome.hero.cta_start') }}</a>
        </div>
    </div>
</nav>
<script>
(function () {
    var desktopMq = window.matchMedia('(min-width: 48rem)');

    function syncWelcomeNavMenu() {
        document.querySelectorAll('.wp-welcome-nav-menu').forEach(function (menu) {
            if (desktopMq.matches) {
                menu.setAttribute('open', '');
            } else {
                menu.removeAttribute('open');
            }
        });
    }

    if (typeof desktopMq.addEventListener === 'function') {
        desktopMq.addEventListener('change', syncWelcomeNavMenu);
    } else if (typeof desktopMq.addListener === 'function') {
        desktopMq.addListener(syncWelcomeNavMenu);
    }

    syncWelcomeNavMenu();

    document.querySelectorAll('.wp-welcome-nav-menu').forEach(function (menu) {
        menu.querySelectorAll('.wp-welcome-nav-links a, .wp-welcome-nav-menu__auth a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (! desktopMq.matches) {
                    menu.removeAttribute('open');
                }
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (desktopMq.matches) {
            return;
        }

        document.querySelectorAll('.wp-welcome-nav-menu[open]').forEach(function (menu) {
            if (! menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });
})();
</script>
