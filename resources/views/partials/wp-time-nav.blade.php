@props(['alarmCount' => null, 'ciaoFailCount' => null, 'pendingAbsenceCount' => null])

{{--
  Time-balk: alleen links die niet in de Time-zijbalk staan.
  Zijbalk (normaal): Clock Points, Aanwezigheid, Uurrooster, Aanvragen.
  Zijbalk (Checkmate): Clock Points, Aanwezigheid, Uren, CIAO.
--}}
@php
    $tenantId = \App\Support\Tenancy::id();
    $tenant = $tenantId !== null ? \App\Models\Tenant::query()->find($tenantId) : null;
    $ciaoEnabled = $tenant instanceof \App\Models\Tenant && $tenant->presenceComplianceEnabled();
    $checkmateMode = \App\Support\Checkmate\CheckmateMode::isActive($tenant);

    if ($ciaoEnabled && $ciaoFailCount === null) {
        $ciaoFailCount = app(\App\Actions\Time\CountFailedPresenceSubmissionsAction::class)
            ->handle((int) $tenantId);
    }

    // Checkmate: alles zit al in de zijbalk → geen balk.
    $showShifts = ! $checkmateMode && ! request()->routeIs('time.shifts.*');
    $showShiftTypes = ! $checkmateMode && ! request()->routeIs('time.shift-types.*');
    $showHolidays = ! $checkmateMode && ! request()->routeIs('time.holidays.*');
    $showStaffing = ! $checkmateMode && ! request()->routeIs('time.staffing.*');
    $showAlarms = ! $checkmateMode && ! request()->routeIs('time.alarms.*');
    $showCiao = $ciaoEnabled && ! $checkmateMode && ! request()->routeIs('time.ciao.*');
    $hasLinks = $showShifts || $showShiftTypes || $showHolidays || $showStaffing || $showAlarms || $showCiao;
@endphp

@if ($hasLinks)
    <nav class="wp-toolbar-group wp-toolbar-group--edit wp-time-nav" aria-label="{{ __('time.nav.label') }}">
        @if ($showShifts)
            <a href="{{ route('time.shifts.index') }}" class="btn btn--sm btn--surface">
                {{ __('time.nav.shifts') }}
            </a>
        @endif
        @if ($showShiftTypes)
            <a href="{{ route('time.shift-types.index') }}" class="btn btn--sm btn--surface">
                {{ __('time.nav.shift_types') }}
            </a>
        @endif
        @if ($showHolidays)
            <a href="{{ route('time.holidays.index') }}" class="btn btn--sm btn--surface">
                {{ __('time.nav.holidays') }}
            </a>
        @endif
        @if ($showStaffing)
            <a href="{{ route('time.staffing.index') }}" class="btn btn--sm btn--surface">
                {{ __('time.nav.staffing') }}
            </a>
        @endif
        @if ($showAlarms)
            <a href="{{ route('time.alarms.index') }}" class="btn btn--sm btn--surface wp-time-nav__alarms">
                {{ __('time.nav.alarms') }}
                @if (($alarmCount ?? 0) > 0)
                    <span class="wp-pill wp-pill--progress wp-time-nav__alarm-count">{{ $alarmCount }}</span>
                @endif
            </a>
        @endif
        @if ($showCiao)
            <a href="{{ route('time.ciao.index') }}" class="btn btn--sm btn--surface wp-time-nav__alarms">
                {{ __('time.nav.ciao') }}
                @if (($ciaoFailCount ?? 0) > 0)
                    <span class="wp-pill wp-pill--new wp-time-nav__alarm-count">{{ $ciaoFailCount }}</span>
                @endif
            </a>
        @endif
    </nav>
@endif
