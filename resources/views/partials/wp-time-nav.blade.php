@props(['alarmCount' => null, 'ciaoFailCount' => null, 'pendingAbsenceCount' => null, 'showShiftActions' => false])

{{--
  Time-balk: alleen links die niet in de Time-zijbalk staan.
  Links (groen): geklokte uren, overuren, alarmen, CIAO.
  Midden (oranje): shifttypes, feestdagen, minimumbezetting.
  Op geklokte uren rechts (blauw): manueel inklokken en rapport.
  Zijbalk (Checkmate): Clock Points, Aanwezigheid, Uren, CIAO. Geen balk.
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

    $rosterOn = $tenant instanceof \App\Models\Tenant && $tenant->timeRosterEnabled();
    $showShifts = ! $checkmateMode && ! request()->routeIs('time.shifts.*');
    $showOvertime = $rosterOn && ! $checkmateMode && ! request()->routeIs('time.overtime.*');
    $showShiftTypes = $rosterOn && ! $checkmateMode && ! request()->routeIs('time.shift-types.*');
    $showHolidays = $rosterOn && ! $checkmateMode && ! request()->routeIs('time.holidays.*');
    $showStaffing = $rosterOn && ! $checkmateMode && ! request()->routeIs('time.staffing.*');
    $showAlarms = ! $checkmateMode && ! request()->routeIs('time.alarms.*');
    $showCiao = $ciaoEnabled && ! $checkmateMode && ! request()->routeIs('time.ciao.*');
    $showClockGroup = $showShifts || $showOvertime || $showAlarms || $showCiao;
    $showPlanGroup = $showShiftTypes || $showHolidays || $showStaffing;
    $canManualClockIn = $showShiftActions
        && ! $checkmateMode
        && (auth()->user()?->can('manualClockIn', \App\Models\WorkShift::class) ?? false);
    $canExportShifts = $showShiftActions
        && ! ($isWeek ?? false)
        && filled($exportUrl ?? null)
        && filled($printUrl ?? null);
@endphp

@if ($showClockGroup || $showPlanGroup || $canManualClockIn || $canExportShifts)
    <div class="wp-time-nav-split">
        @if ($showClockGroup)
            <nav class="wp-toolbar-group wp-toolbar-group--edit wp-time-nav wp-time-nav--clock" aria-label="{{ __('time.nav.clock_group') }}">
                @if ($showShifts)
                    <a href="{{ route('time.shifts.index') }}" class="btn btn--sm btn--surface">
                        {{ __('time.nav.shifts') }}
                    </a>
                @endif
                @if ($showOvertime)
                    <a href="{{ route('time.overtime.index') }}" class="btn btn--sm btn--surface">
                        {{ __('time.nav.overtime') }}
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
        @if ($showPlanGroup)
            <nav class="wp-toolbar-group wp-toolbar-group--edit wp-time-nav wp-time-nav--plan" aria-label="{{ __('time.nav.plan_group') }}">
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
            </nav>
        @endif
        @if ($canManualClockIn || $canExportShifts)
            <div class="wp-toolbar-group wp-toolbar-group--edit wp-time-nav wp-time-nav--actions" aria-label="{{ __('time.nav.shift_actions') }}">
                @if ($canManualClockIn)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openManualClockIn">
                        {{ __('time.manual_clock_in.button') }}
                    </button>
                @endif
                @if ($canExportShifts)
                    <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
                @endif
            </div>
        @endif
    </div>
@endif
