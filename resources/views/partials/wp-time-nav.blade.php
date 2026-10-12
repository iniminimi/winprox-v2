@props(['alarmCount' => null, 'ciaoFailCount' => null, 'pendingAbsenceCount' => null])

@php
    $tenantId = \App\Support\Tenancy::id();
    $tenant = $tenantId !== null ? \App\Models\Tenant::query()->find($tenantId) : null;
    $ciaoEnabled = $tenant instanceof \App\Models\Tenant && $tenant->presenceComplianceEnabled();
    $checkmateMode = \App\Support\Checkmate\CheckmateMode::isActive($tenant);
    if ($ciaoEnabled && $ciaoFailCount === null) {
        $ciaoFailCount = app(\App\Actions\Time\CountFailedPresenceSubmissionsAction::class)
            ->handle((int) $tenantId);
    }
    if ($pendingAbsenceCount === null && ! $checkmateMode && $tenant instanceof \App\Models\Tenant && $tenant->hasTimeModule()) {
        $pendingAbsenceCount = app(\App\Actions\Time\CountPendingAbsenceRequestsAction::class)
            ->handle((int) $tenantId);
    }

    $moreCount = ($checkmateMode ? 0 : (int) ($alarmCount ?? 0))
        + ($ciaoEnabled ? (int) ($ciaoFailCount ?? 0) : 0);
    $moreActive = request()->routeIs('time.shift-types.*', 'time.alarms.*', 'time.clock-points.*', 'time.ciao.*');
@endphp

<nav class="wp-cluster wp-cluster--tight wp-time-nav" aria-label="{{ __('time.nav.label') }}">
    @if (! request()->routeIs('time.presence.index'))
        <a href="{{ route('time.presence.index') }}" class="btn btn--sm btn--surface">
            {{ __('time.nav.presence') }}
        </a>
    @endif
    @if (! $checkmateMode)
        @if (! request()->routeIs('time.schedule.*'))
            <a href="{{ route('time.schedule.index') }}" class="btn btn--sm btn--surface">
                {{ __('time.nav.schedule') }}
            </a>
        @endif
        @if (! request()->routeIs('time.absence-requests.*'))
            <a href="{{ route('time.absence-requests.index') }}" class="btn btn--sm btn--surface wp-time-nav__alarms">
                {{ __('time.nav.absence_requests') }}
                @if (($pendingAbsenceCount ?? 0) > 0)
                    <span class="wp-pill wp-pill--progress wp-time-nav__alarm-count">{{ $pendingAbsenceCount }}</span>
                @endif
            </a>
        @endif
    @endif
    @if (! request()->routeIs('time.shifts.*'))
        <a href="{{ route('time.shifts.index') }}" class="btn btn--sm btn--surface">
            {{ __('time.nav.shifts') }}
        </a>
    @endif

    <details class="wp-time-nav-more">
        <summary @class(['btn', 'btn--sm', 'wp-time-nav__alarms', $moreActive ? 'btn--primary' : 'btn--surface'])>
            {{ __('time.nav.more') }}
            @if ($moreCount > 0)
                <span class="wp-pill wp-pill--progress wp-time-nav__alarm-count">{{ $moreCount }}</span>
            @endif
            <x-wp-icon name="chevron-down" class="wp-icon wp-time-nav-more__chevron" />
        </summary>
        <div class="wp-time-nav-more__menu">
            @if (! $checkmateMode)
                @if (! request()->routeIs('time.shift-types.*'))
                    <a href="{{ route('time.shift-types.index') }}" class="wp-time-nav-more__link">
                        <span>{{ __('time.nav.shift_types') }}</span>
                    </a>
                @endif
                @if (! request()->routeIs('time.alarms.*'))
                    <a href="{{ route('time.alarms.index') }}" class="wp-time-nav-more__link">
                        <span>{{ __('time.nav.alarms') }}</span>
                        @if (($alarmCount ?? 0) > 0)
                            <span class="wp-pill wp-pill--progress wp-time-nav__alarm-count">{{ $alarmCount }}</span>
                        @endif
                    </a>
                @endif
            @endif
            @if (! request()->routeIs('time.clock-points.*'))
                <a href="{{ route('time.clock-points.index') }}" class="wp-time-nav-more__link">
                    <span>{{ __('time.nav.clock_points') }}</span>
                </a>
            @endif
            @if ($ciaoEnabled && ! request()->routeIs('time.ciao.*'))
                <a href="{{ route('time.ciao.index') }}" class="wp-time-nav-more__link">
                    <span>{{ __('time.nav.ciao') }}</span>
                    @if (($ciaoFailCount ?? 0) > 0)
                        <span class="wp-pill wp-pill--new wp-time-nav__alarm-count">{{ $ciaoFailCount }}</span>
                    @endif
                </a>
            @endif
        </div>
    </details>
</nav>
