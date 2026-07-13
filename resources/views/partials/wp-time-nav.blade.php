@props(['alarmCount' => null])

<nav class="wp-cluster wp-cluster--tight wp-time-nav" aria-label="{{ __('time.nav.label') }}">
    <a href="{{ route('time.presence.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.presence.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.presence') }}
    </a>
    <a href="{{ route('time.alarms.index') }}" @class(['btn', 'btn--sm', 'wp-time-nav__alarms', request()->routeIs('time.alarms.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.alarms') }}
        @if (($alarmCount ?? 0) > 0)
            <span class="wp-pill wp-pill--progress wp-time-nav__alarm-count">{{ $alarmCount }}</span>
        @endif
    </a>
    <a href="{{ route('time.shifts.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.shifts.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.shifts') }}
    </a>
    <a href="{{ route('time.clock-points.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.clock-points.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.clock_points') }}
    </a>
</nav>
