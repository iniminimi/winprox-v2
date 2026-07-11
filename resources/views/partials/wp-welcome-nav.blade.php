@php
    $onWelcome = request()->routeIs('welcome');
    $welcomeSection = static fn (string $id): string => $onWelcome ? "#{$id}" : route('welcome')."#{$id}";
@endphp

<nav class="wp-welcome-nav" aria-label="{{ __('welcome.meta_title') }}">
    <div class="wp-welcome-nav-inner">
        @include('partials.wp-welcome-brand')
        <details class="wp-welcome-nav-menu">
            <summary class="wp-welcome-nav-menu__toggle btn btn--ghost btn--sm">
                <x-wp-icon name="menu" class="wp-icon" aria-hidden="true" />
                {{ __('welcome.nav.menu') }}
            </summary>
            <div class="wp-welcome-nav-menu__panel">
                <div class="wp-welcome-nav-links">
                    <a href="{{ $welcomeSection('producten') }}">{{ __('welcome.nav.products') }}</a>
                    <a href="{{ $welcomeSection('platform') }}">{{ __('welcome.nav.platform') }}</a>
                    <a href="{{ $welcomeSection('esg') }}">{{ __('welcome.nav.esg') }}</a>
                    <a href="{{ $welcomeSection('qr') }}">{{ __('welcome.nav.qr') }}</a>
                    <a href="{{ $welcomeSection('organisaties') }}">{{ __('welcome.nav.sectors') }}</a>
                    <a href="{{ $welcomeSection('video') }}">{{ __('welcome.nav.video') }}</a>
                    <a href="{{ route('pricing') }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('welcome.nav.pricing') }}</a>
                </div>
            </div>
        </details>
        <div class="wp-welcome-nav-actions">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
            <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
            <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.hero.cta_start') }}</a>
        </div>
    </div>
</nav>
<script>
document.querySelectorAll('.wp-welcome-nav-menu').forEach(function (menu) {
    menu.querySelectorAll('.wp-welcome-nav-links a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 47.99rem)').matches) {
                menu.removeAttribute('open');
            }
        });
    });
});
</script>
