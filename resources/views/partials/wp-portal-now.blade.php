@php
    $workerName = trim(($verifiedWorker?->last_name ?? '').' '.($verifiedWorker?->first_name ?? ''));
    $signedInDate = $signedInAt?->format('d-m-Y');
    $signedInTime = $signedInAt?->format('H:i');
    $clockedInTime = $openShift?->clock_in_at?->format('H:i');
    $breakSince = $openShift?->openBreak?->started_at?->format('H:i');
    $presencePoint = $openShift?->currentClockPoint();
    $clockInPlace = $presencePoint?->location?->name
        ? $presencePoint->location->name.($presencePoint->name ? ' · '.$presencePoint->name : '')
        : ($presencePoint?->name ?? '');
    $openElsewhere = $openShift !== null && $presencePoint !== null
        && (int) $presencePoint->id !== (int) $clockPointId;
    $startedElsewhere = $openShift !== null
        && $openShift->hasLocationHops()
        && (int) $openShift->clock_in_clock_point_id !== (int) ($presencePoint?->id ?? 0);
    $clockedOutTime = $openShift === null ? $lastClosedShift?->clock_out_at?->format('H:i') : null;
    $onBreak = $openShift?->openBreak !== null;
    $hasVisit = ($gpsVisits ?? false) && $openShift?->openVisit;
    $visitPlace = $hasVisit
        ? trim(($openShift->openVisit->location?->name ?? '').' · '.($openShift->openVisit->unit?->name ?? ''), ' ·')
        : '';
    $nextStop = filled($onSiteGuidance['next'] ?? null) ? $onSiteGuidance['next'] : null;
    $hereDone = ($onSiteGuidance['remaining_here'] ?? true) === false && $nextStop !== null;
    $primary = null;
    if ($openShift === null && ($canPunch ?? false)) {
        $primary = ['clockIn', __('time.portal.clock.in')];
    } elseif ($onBreak) {
        $primary = ['endBreak', __('time.portal.clock.end_break')];
    } elseif ($openElsewhere && ($canPunch ?? false)) {
        $primary = ['transferToThisClockPoint', __('time.portal.clock.transfer_here')];
    }
@endphp
<div class="wp-portal-now" x-data="{ open: false }">
    <p class="wp-portal-now__kicker">{{ __('time.portal.now.kicker') }}</p>
    <div class="wp-portal-now__head">
        <strong class="wp-portal-now__name">{{ $workerName }}</strong>
        @if ($primary !== null)
            @if ($primary[0] === 'endBreak')
                <button type="button" class="btn btn--primary btn--sm" wire:click="endBreak">{{ $primary[1] }}</button>
            @else
                <button type="button" class="btn btn--primary btn--sm" @click="withGps('{{ $primary[0] }}')">{{ $primary[1] }}</button>
            @endif
        @endif
    </div>

    <div class="wp-portal-now__more">
        <button
            type="button"
            class="wp-settings-section-toggle wp-portal-now__disclosure-toggle"
            @click="open = !open"
            :aria-expanded="open"
        >
            <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
            <span class="wp-portal-now__disclosure-title">{{ __('time.portal.now.more') }}</span>
        </button>
        <div class="wp-disclosure-panel wp-portal-now__more-panel" x-show="open" x-cloak>
            <div class="wp-portal-now__sign-out-row">
                <p class="wp-portal-now__meta">
                    {{ __('time.portal.clock.signed_in_since', [
                        'date' => $signedInDate ?? '—',
                        'time' => $signedInTime ?? '—',
                    ]) }}
                </p>
                @include('partials.wp-portal-sign-out', ['signOutMethod' => 'signOut'])
            </div>

            @if ($openShift === null)
                <p class="wp-portal-now__meta">
                    @if ($clockedOutTime)
                        {{ __('time.portal.clock.clocked_out_at', ['time' => $clockedOutTime]) }}
                    @else
                        {{ __('time.portal.clock.not_clocked_in') }}
                    @endif
                    @include('partials.wp-portal-clock-alert', ['alert' => $todayClockAlert ?? null])
                </p>
                @unless (($canPunch ?? false) || $clockedOutTime)
                    <p class="wp-portal-now__meta">{{ __('time.portal.clock.scan_to_clock_in') }}</p>
                @endunless
            @else
                <p class="wp-portal-now__meta">
                    {{ __('time.portal.clock.clocked_in_at_tenant', ['tenant' => $tenantName, 'time' => $clockedInTime ?? '—']) }}
                    @include('partials.wp-portal-clock-alert', ['alert' => $todayClockAlert ?? null])
                </p>
                @if ($openShift->isManuallyClockedIn())
                    <p class="wp-portal-now__meta">{{ __('time.manual_clock_in.badge') }}</p>
                @endif
                @if ($onBreak)
                    <p class="wp-portal-now__state">{{ __('time.portal.clock.on_break_since', ['time' => $breakSince ?? '—']) }}</p>
                @elseif ($hasVisit && $visitPlace !== '')
                    <p class="wp-portal-now__state">{{ __('time.portal.clock.working_at', ['place' => $visitPlace]) }}</p>
                @endif
                @if ($hereDone)
                    <p class="wp-portal-now__meta">{{ __('time.portal.now.here_done') }}</p>
                @endif
                @if ($nextStop)
                    <p class="wp-portal-now__next">{{ __('time.portal.now.next_stop', ['name' => $nextStop]) }}</p>
                @endif
                @if ($startedElsewhere && $openShift->clockInClockPoint)
                    <p class="wp-portal-now__meta">{{ __('time.portal.clock.started_at', ['place' => $openShift->clockInClockPoint->name]) }}</p>
                @endif
                @if ($openElsewhere)
                    @if ($clockInPlace !== '')
                        <p class="wp-portal-now__meta">{{ __('time.portal.clock.started_at', ['place' => $clockInPlace]) }}</p>
                    @endif
                    <p class="wp-portal-now__meta">{{ __('time.portal.clock.open_elsewhere_hint') }}</p>
                @endif
            @endif

            <div class="wp-portal-now__more-actions">
                @if ($openShift !== null && ($canPunch ?? false))
                    <button type="button" class="btn btn--surface btn--sm" @click="withGps('clockOut')">
                        {{ __('time.portal.clock.out') }}
                    </button>
                @endif
                @if ($hasVisit)
                    <button type="button" class="btn btn--surface btn--sm" @click="withFreshGps('endWorkVisit')">
                        {{ __('time.portal.clock.stop_work') }}
                    </button>
                @endif
                @if ($openShift !== null && ! $onBreak && ! $openElsewhere)
                    <button type="button" class="btn btn--surface btn--sm" wire:click="startBreak">
                        {{ __('time.portal.clock.start_break') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
