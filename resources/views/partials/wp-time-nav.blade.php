<nav class="wp-cluster wp-cluster--tight wp-time-nav" aria-label="{{ __('time.nav.label') }}">
    <a href="{{ route('time.presence.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.presence.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.presence') }}
    </a>
    <a href="{{ route('time.shifts.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.shifts.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.shifts') }}
    </a>
    <a href="{{ route('time.clock-points.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.clock-points.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.clock_points') }}
    </a>
</nav>
