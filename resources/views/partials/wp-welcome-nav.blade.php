@php
    $sectorLandings = \App\Enums\PromoLanding::cases();
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
                <div class="wp-welcome-nav-links wp-welcome-nav-links--mobile">
                    <a href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>
                    <a href="{{ route('product.features') }}" @if (request()->routeIs('product.features')) aria-current="page" @endif>{{ __('welcome.nav.features_overview') }}</a>
                    <a href="{{ route('faq.public') }}" @if (request()->routeIs('faq.public')) aria-current="page" @endif>{{ __('welcome.nav.faq') }}</a>
                    @foreach ($sectorLandings as $sectorLanding)
                        <a
                            href="{{ route($sectorLanding->routeName()) }}"
                            @if (request()->routeIs($sectorLanding->routeName())) aria-current="page" @endif
                        >{{ __($sectorLanding->labelKey()) }}</a>
                    @endforeach
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>{{ __('welcome.nav.about') }}</a>
                    <a href="{{ route('product.technical') }}" @if (request()->routeIs('product.technical')) aria-current="page" @endif>{{ __('welcome.nav.technical_sheet') }}</a>
                    <a href="{{ route('product.api_webhooks') }}" @if (request()->routeIs('product.api_webhooks')) aria-current="page" @endif>{{ __('welcome.nav.api_webhooks') }}</a>
                </div>

                <div class="wp-welcome-nav-links wp-welcome-nav-links--desktop">
                    <a class="wp-welcome-nav-direct" href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>
                    <a class="wp-welcome-nav-direct" href="{{ route('product.features') }}" @if (request()->routeIs('product.features')) aria-current="page" @endif>{{ __('welcome.nav.features_overview') }}</a>
                    <a class="wp-welcome-nav-direct" href="{{ route('faq.public') }}" @if (request()->routeIs('faq.public')) aria-current="page" @endif>{{ __('welcome.nav.faq') }}</a>

                    <details class="wp-welcome-nav-group">
                        <summary class="wp-welcome-nav-group__toggle">{{ __('welcome.nav.sectors') }}</summary>
                        <div class="wp-welcome-nav-group__panel" role="list">
                            @foreach ($sectorLandings as $sectorLanding)
                                <a
                                    href="{{ route($sectorLanding->routeName()) }}"
                                    role="listitem"
                                    @if (request()->routeIs($sectorLanding->routeName())) aria-current="page" @endif
                                >{{ __($sectorLanding->labelKey()) }}</a>
                            @endforeach
                        </div>
                    </details>

                    <details class="wp-welcome-nav-group">
                        <summary class="wp-welcome-nav-group__toggle">{{ __('welcome.nav.more') }}</summary>
                        <div class="wp-welcome-nav-group__panel" role="list">
                            <a href="{{ route('about') }}" role="listitem" @if (request()->routeIs('about')) aria-current="page" @endif>{{ __('welcome.nav.about') }}</a>
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
