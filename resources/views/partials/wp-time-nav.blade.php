@props(['alarmCount' => null, 'ciaoFailCount' => null])

@php
    if ($ciaoFailCount === null) {
        $ciaoFailCount = app(\App\Actions\Time\CountFailedPresenceSubmissionsAction::class)
            ->handle((int) (\App\Support\Tenancy::id() ?? 0));
    }
@endphp

<nav class="wp-cluster wp-cluster--tight wp-time-nav" aria-label="{{ __('time.nav.label') }}">
    <a href="{{ route('time.presence.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.presence.index') ? 'btn--primary' : 'btn--surface'])>
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
    <a href="{{ route('time.ciao.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.ciao.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.ciao') }}
        @if ($ciaoFailCount > 0)
            <span class="wp-pill wp-pill--new wp-time-nav__ciao-count">{{ $ciaoFailCount }}</span>
        @endif
    </a>
    <a href="{{ route('time.clock-points.index') }}" @class(['btn', 'btn--sm', request()->routeIs('time.clock-points.*') ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.nav.clock_points') }}
    </a>
</nav>
