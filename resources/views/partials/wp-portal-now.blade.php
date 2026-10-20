@php
    $workerName = trim(($verifiedWorker?->last_name ?? '').' '.($verifiedWorker?->first_name ?? ''));
    $clockedInTime = $openShift?->clock_in_at?->format('H:i');
    $breakSince = $openShift?->openBreak?->started_at?->format('H:i');
    $presencePoint = $openShift?->currentClockPoint();
    $clockInPlace = $presencePoint?->location?->name
        ? $presencePoint->location->name.($presencePoint->name ? ' · '.$presencePoint->name : '')
        : ($presencePoint?->name ?? '');
    $openElsewhere = $openShift !== null && $presencePoint !== null
        && ($portalClockPoint ?? null) !== null
        && ! $openShift->isPresentAtSamePlace($portalClockPoint);
    $startedElsewhere = $openShift !== null
        && $openShift->hasLocationHops()
        && (int) $openShift->clock_in_clock_point_id !== (int) ($presencePoint?->id ?? 0);
    $clockedOutTime = $openShift === null ? $lastClosedShift?->clock_out_at?->format('H:i') : null;
    $onBreak = $openShift?->openBreak !== null;
    $breakTaken = $openShift !== null && $openShift->breaks->isNotEmpty();
    $requiredBreak = (int) ($openShift?->team?->required_break_minutes ?? 0);
    $breakReminderHours = (int) config('time.break_reminder_hours', 6);
    $clockedInLongEnough = $openShift?->clock_in_at !== null
        && $openShift->clock_in_at->lte(now()->subHours($breakReminderHours));
    $hasVisit = ($gpsVisits ?? false) && $openShift?->openVisit;
    $visitPlace = $hasVisit
        ? trim(($openShift->openVisit->location?->name ?? '').' · '.($openShift->openVisit->unit?->name ?? ''), ' ·')
        : '';
    $nextStop = filled($onSiteGuidance['next'] ?? null) ? $onSiteGuidance['next'] : null;
    $hereDone = ($onSiteGuidance['remaining_here'] ?? true) === false && $nextStop !== null;
    $absenceClockInKind = filled($absenceClockInKind ?? null) ? (string) $absenceClockInKind : null;
    $directForever = (bool) ($checkmateMode ?? false);
    $grantSeconds = $directForever ? 0 : (int) ($punchGrantSeconds ?? 0);

    if ($absenceClockInKind !== null) {
        $cardState = 'absence';
        $statusIcon = 'alert-triangle';
        $statusLine = __('time.portal.clock.absence_clock_in', [
            'kind' => __('time.presence.attention.absence_kinds.'.$absenceClockInKind),
        ]);
    } elseif ($onBreak) {
        $cardState = 'break';
        $statusIcon = 'hourglass';
        $statusLine = __('time.portal.clock.on_break_since', ['time' => $breakSince ?? '—']);
    } elseif ($openElsewhere) {
        $cardState = 'in';
        $statusIcon = 'map-pin';
        $statusLine = __('time.portal.now.status_elsewhere');
    } elseif ($openShift !== null) {
        $cardState = 'in';
        $statusIcon = 'check';
        $statusLine = $clockInPlace !== ''
            ? __('time.portal.now.status_in_at', ['time' => $clockedInTime ?? '—', 'place' => $clockInPlace])
            : __('time.portal.now.status_in', ['time' => $clockedInTime ?? '—']);
    } elseif ($clockedOutTime) {
        $cardState = 'out';
        $statusIcon = 'clock';
        $statusLine = __('time.portal.clock.clocked_out_at', ['time' => $clockedOutTime]);
    } else {
        $cardState = 'out';
        $statusIcon = 'clock';
        $statusLine = __('time.portal.now.status_out');
    }

    if ($onBreak) {
        $action = ['method' => 'endBreak', 'label' => __('time.portal.clock.end_break'), 'needsScan' => false];
    } elseif ($openShift === null) {
        $action = ['method' => 'clockIn', 'label' => __('time.portal.clock.in'), 'needsScan' => true];
    } elseif ($openElsewhere) {
        $action = ['method' => 'transferToThisClockPoint', 'label' => __('time.portal.clock.transfer_here'), 'needsScan' => true];
    } else {
        $action = ['method' => 'clockOut', 'label' => __('time.portal.clock.out'), 'needsScan' => true];
    }
@endphp
<div @class([
    'wp-portal-now',
    'wp-portal-now--clocked' => $cardState === 'in',
    'wp-portal-now--break' => $cardState === 'break',
    'wp-portal-now--absence' => $cardState === 'absence',
]) x-data="{ open: false }">
    <p class="wp-portal-now__kicker">{{ $statusLine }}</p>
    <div class="wp-portal-now__head">
        <button
            type="button"
            class="wp-settings-section-toggle wp-portal-now__disclosure-toggle"
            @click="open = !open"
            :aria-expanded="open"
            aria-label="{{ __('time.portal.now.toggle') }}"
            data-manual-capture-trigger="portal-now-toggle"
        >
            <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
            <strong class="wp-portal-now__name">{{ $workerName }}</strong>
        </button>
        @if (! $action['needsScan'])
            <button type="button" class="btn btn--primary btn--sm" wire:click="endBreak" data-manual-capture-trigger="portal-primary-action">{{ $action['label'] }}</button>
        @else
            <div
                class="wp-portal-now__punch"
                x-data="{ direct: @js($directForever), left: @js($grantSeconds) }"
                x-init="if (!direct && left > 0) { const id = setInterval(() => { left -= 1; if (left <= 0) { left = 0; clearInterval(id) } }, 1000) }"
            >
                <button
                    type="button"
                    class="btn btn--primary btn--sm"
                    x-show="direct || left > 0"
                    x-cloak
                    @click="withGps('{{ $action['method'] }}')"
                    data-manual-capture-trigger="portal-primary-action"
                >{{ $action['label'] }}</button>
                <div
                    x-show="!direct && left <= 0"
                    x-cloak
                    x-data="wpPortalQrScanner({
                        i18n: {
                            unsupported: @js(__('time.portal.scan.error_unsupported')),
                            denied: @js(__('time.portal.scan.error_denied')),
                            invalid: @js(__('time.portal.scan.error_invalid')),
                            slow: @js(__('time.portal.scan.slow_hint')),
                        },
                    })"
                >
                    <button type="button" class="btn btn--primary btn--sm" @click="openScanner()" data-manual-capture-trigger="portal-scan-qr">
                        <x-wp-icon name="qr" class="wp-icon" />
                        <span>{{ $action['label'] }}</span>
                    </button>
                    <template x-teleport="body">
                        <div class="wp-scan-overlay" x-show="scannerOpen" x-cloak @keydown.escape.window="closeScanner()">
                            <div class="wp-scan-dialog" role="dialog" aria-modal="true" aria-label="{{ __('time.portal.scan.title') }}">
                                <p class="wp-scan-title">{{ __('time.portal.scan.title') }}</p>
                                <div class="wp-scan-viewport">
                                    <video x-ref="scanVideo" playsinline muted autoplay></video>
                                    <span class="wp-scan-frame" aria-hidden="true"></span>
                                </div>
                                <p class="wp-scan-hint">{{ __('time.portal.scan.hint') }}</p>
                                <p class="wp-scan-hint" x-show="slowHint" x-text="i18n.slow" x-cloak></p>
                                <p class="wp-scan-error" x-show="errorKey !== null" x-text="errorText" x-cloak></p>
                                <button type="button" class="btn btn--ghost btn--sm" @click="closeScanner()">{{ __('common.button.cancel') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endif
        <x-wp-icon :name="$statusIcon" class="wp-portal-now__status-icon" data-clock-state="{{ $openShift === null ? 'out' : 'in' }}" />
    </div>

    <div class="wp-disclosure-panel wp-portal-now__more-panel" x-show="open" x-cloak>
        @if ($openShift !== null)
            @if ($openShift->isManuallyClockedIn())
                <p class="wp-portal-now__meta">{{ __('time.manual_clock_in.badge') }}</p>
            @endif
            @if (! $onBreak && $hasVisit && $visitPlace !== '')
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
                <p class="wp-portal-now__meta">{{ __('time.portal.clock.elsewhere_body') }}</p>
            @endif
            @include('partials.wp-portal-clock-alert', ['alert' => $todayClockAlert ?? null])
        @else
            @include('partials.wp-portal-clock-alert', ['alert' => $todayClockAlert ?? null])
        @endif

        @if ($hasVisit)
            <div class="wp-portal-now__more-actions">
                <button type="button" class="btn btn--surface btn--sm" @click="withFreshGps('endWorkVisit')">
                    {{ __('time.portal.clock.stop_work') }}
                </button>
            </div>
        @endif

        @if ($openShift !== null && ! $onBreak && ! $breakTaken && ! $openElsewhere)
            <div class="wp-portal-now__divider" role="separator"></div>
            @if ($requiredBreak > 0 && $clockedInLongEnough)
                <p class="wp-portal-now__meta">{{ __('time.portal.clock.break_hint', ['min' => $requiredBreak]) }}</p>
            @endif
            <div class="wp-portal-now__more-actions">
                <button type="button" class="btn btn--surface btn--sm" wire:click="startBreak">
                    {{ __('time.portal.clock.start_break') }}
                </button>
            </div>
        @endif

        <div class="wp-portal-now__divider" role="separator"></div>
        <button
            type="button"
            class="wp-portal-now__sign-out"
            x-data='@json(['clocked' => $openShift !== null, 'message' => __('time.portal.clock.sign_out_while_clocked')])'
            @click="window.wpFieldFlush?.(); if (!clocked || confirm(message)) { $wire.signOut() }"
        >{{ __('portal.worker.sign_out') }}</button>
    </div>
</div>
