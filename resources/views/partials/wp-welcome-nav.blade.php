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
                {{-- Mobiel: platte lijst --}}
                <div class="wp-welcome-nav-links wp-welcome-nav-links--mobile">
                    <a href="{{ $welcomeSection('producten') }}">{{ __('welcome.nav.products') }}</a>
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>{{ __('welcome.nav.about') }}</a>
                    <a href="{{ $welcomeSection('flow') }}">{{ __('welcome.nav.how') }}</a>
                    <a href="{{ $welcomeSection('qr') }}">{{ __('welcome.nav.qr') }}</a>
                    <a href="{{ $welcomeSection('platform') }}">{{ __('welcome.nav.platform') }}</a>
                    <a href="{{ $welcomeSection('esg') }}">{{ __('welcome.nav.esg') }}</a>
                    <a href="{{ $welcomeSection('iot') }}">{{ __('welcome.nav.iot') }}</a>
                    <a href="{{ $welcomeSection('organisaties') }}">{{ __('welcome.nav.sectors') }}</a>
                    <a href="{{ $welcomeSection('video') }}">{{ __('welcome.nav.video') }}</a>
                    <a href="{{ route('faq.public') }}" @if (request()->routeIs('faq.public')) aria-current="page" @endif>{{ __('welcome.nav.faq') }}</a>
                    <a href="{{ route('product.features') }}" @if (request()->routeIs('product.features')) aria-current="page" @endif>{{ __('welcome.nav.features_overview') }}</a>
                    <a href="{{ route('product.technical') }}" @if (request()->routeIs('product.technical')) aria-current="page" @endif>{{ __('welcome.nav.technical_sheet') }}</a>
                    <a href="{{ route('product.api_webhooks') }}" @if (request()->routeIs('product.api_webhooks')) aria-current="page" @endif>{{ __('welcome.nav.api_webhooks') }}</a>
                    <a href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>
                </div>

                {{-- Desktop: gegroepeerd --}}
                <div class="wp-welcome-nav-links wp-welcome-nav-links--desktop">
                    <details class="wp-welcome-nav-group">
                        <summary class="wp-welcome-nav-group__toggle">{{ __('welcome.nav.group_products') }}</summary>
                        <div class="wp-welcome-nav-group__panel" role="list">
                            <a href="{{ $welcomeSection('producten') }}" role="listitem">{{ __('welcome.nav.products') }}</a>
                            <a href="{{ $welcomeSection('esg') }}" role="listitem">{{ __('welcome.nav.esg') }}</a>
                            <a href="{{ $welcomeSection('iot') }}" role="listitem">{{ __('welcome.nav.iot') }}</a>
                        </div>
                    </details>

                    <details class="wp-welcome-nav-group">
                        <summary class="wp-welcome-nav-group__toggle">{{ __('welcome.nav.group_how') }}</summary>
                        <div class="wp-welcome-nav-group__panel" role="list">
                            <a href="{{ $welcomeSection('flow') }}" role="listitem">{{ __('welcome.nav.how') }}</a>
                            <a href="{{ $welcomeSection('qr') }}" role="listitem">{{ __('welcome.nav.qr') }}</a>
                            <a href="{{ $welcomeSection('platform') }}" role="listitem">{{ __('welcome.nav.platform') }}</a>
                            <a href="{{ $welcomeSection('video') }}" role="listitem">{{ __('welcome.nav.video') }}</a>
                        </div>
                    </details>

                    <a class="wp-welcome-nav-direct" href="{{ $welcomeSection('organisaties') }}">{{ __('welcome.nav.sectors') }}</a>
                    <a class="wp-welcome-nav-direct" href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>

                    <details class="wp-welcome-nav-group">
                        <summary class="wp-welcome-nav-group__toggle">{{ __('welcome.nav.group_more') }}</summary>
                        <div class="wp-welcome-nav-group__panel" role="list">
                            <a href="{{ route('about') }}" role="listitem" @if (request()->routeIs('about')) aria-current="page" @endif>{{ __('welcome.nav.about') }}</a>
                            <a href="{{ route('faq.public') }}" role="listitem" @if (request()->routeIs('faq.public')) aria-current="page" @endif>{{ __('welcome.nav.faq') }}</a>
                            <a href="{{ route('product.features') }}" role="listitem" @if (request()->routeIs('product.features')) aria-current="page" @endif>{{ __('welcome.nav.features_overview') }}</a>
                            <a href="{{ route('product.technical') }}" role="listitem" @if (request()->routeIs('product.technical')) aria-current="page" @endif>{{ __('welcome.nav.technical_sheet') }}</a>
                            <a href="{{ route('product.api_webhooks') }}" role="listitem" @if (request()->routeIs('product.api_webhooks')) aria-current="page" @endif>{{ __('welcome.nav.api_webhooks') }}</a>
                        </div>
                    </details>
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

    function closeDesktopGroups(except) {
        document.querySelectorAll('.wp-welcome-nav-group[open]').forEach(function (group) {
            if (except && group === except) {
                return;
            }
            group.removeAttribute('open');
        });
    }

    function syncWelcomeNavMenu() {
        document.querySelectorAll('.wp-welcome-nav-menu').forEach(function (menu) {
            if (desktopMq.matches) {
                menu.setAttribute('open', '');
            } else {
                menu.removeAttribute('open');
            }
        });
        closeDesktopGroups();
    }

    if (typeof desktopMq.addEventListener === 'function') {
        desktopMq.addEventListener('change', syncWelcomeNavMenu);
    } else if (typeof desktopMq.addListener === 'function') {
        desktopMq.addListener(syncWelcomeNavMenu);
    }

    syncWelcomeNavMenu();

    document.querySelectorAll('.wp-welcome-nav-group').forEach(function (group) {
        group.addEventListener('toggle', function () {
            if (group.open) {
                closeDesktopGroups(group);
            }
        });
    });

    document.querySelectorAll('.wp-welcome-nav-menu').forEach(function (menu) {
        menu.querySelectorAll('.wp-welcome-nav-links a, .wp-welcome-nav-menu__auth a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (! desktopMq.matches) {
                    menu.removeAttribute('open');
                }
                closeDesktopGroups();
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (desktopMq.matches) {
            document.querySelectorAll('.wp-welcome-nav-group[open]').forEach(function (group) {
                if (! group.contains(event.target)) {
                    group.removeAttribute('open');
                }
            });
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
