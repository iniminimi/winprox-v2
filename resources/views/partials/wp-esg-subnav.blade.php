<nav class="wp-cluster wp-cluster--tight" aria-label="{{ __('esg.nav.aria') }}">
    <a href="{{ route('esg.indicators.index') }}"
       class="btn btn--sm {{ request()->routeIs('esg.indicators.*') ? 'btn--primary' : 'btn--ghost' }}">
        {{ __('esg.nav.indicators') }}
    </a>
    <a href="{{ route('esg.measurements.index') }}"
       class="btn btn--sm {{ request()->routeIs('esg.measurements.*') ? 'btn--primary' : 'btn--ghost' }}">
        {{ __('esg.nav.measurements') }}
    </a>
</nav>
