@php
    $workerName = trim(($verifiedWorker?->last_name ?? '').' '.($verifiedWorker?->first_name ?? ''));
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
@endphp
<div class="wp-card wp-card-pad wp-stack-tight">
    <strong class="wp-text-body">{{ $workerName }}</strong>

    <div class="wp-portal-status">
        <p>{{ __('time.portal.clock.signed_in_at', ['time' => $signedInTime ?? '—']) }}</p>
        @include('partials.wp-portal-sign-out', ['signOutMethod' => 'signOut'])
    </div>

    @if ($hasTimeModule)
        @if ($openShift === null)
            @php $clockedOutTime = $lastClosedShift?->clock_out_at?->format('H:i'); @endphp
            <div class="wp-portal-status">
                @if ($clockedOutTime)
                    <p>{{ __('time.portal.clock.clocked_out_at', ['time' => $clockedOutTime]) }}</p>
                @else
                    <p>{{ __('time.portal.clock.not_clocked_in') }}</p>
                @endif
                @if ($canPunch ?? false)
                    <button type="button" class="btn btn--primary btn--sm" @click="withGps('clockIn')">
                        {{ __('time.portal.clock.in') }}
                    </button>
                @endif
            </div>
            @unless (($canPunch ?? false) || $clockedOutTime)
                <p class="wp-muted">{{ __('time.portal.clock.scan_to_clock_in') }}</p>
            @endunless
        @else
            <div class="wp-portal-status">
                <p>{{ __('time.portal.clock.clocked_in_at_tenant', ['tenant' => $tenantName, 'time' => $clockedInTime ?? '—']) }}</p>
                @if ($canPunch ?? false)
                    <button type="button" class="btn btn--primary btn--sm" @click="withGps('clockOut')">
                        {{ __('time.portal.clock.out') }}
                    </button>
                @endif
            </div>
            @if ($openShift->isManuallyClockedIn())
                <p class="wp-muted">{{ __('time.manual_clock_in.badge') }}</p>
            @endif
            @if (($gpsVisits ?? false) && $openShift->openVisit)
                <div class="wp-portal-status">
                    <p>{{ __('time.portal.clock.working_at', ['place' => trim(($openShift->openVisit->location?->name ?? '').' · '.($openShift->openVisit->unit?->name ?? ''), ' ·')]) }}</p>
                    <button type="button" class="btn btn--surface btn--sm" @click="withFreshGps('endWorkVisit')">
                        {{ __('time.portal.clock.stop_work') }}
                    </button>
                </div>
            @endif
            @if ($startedElsewhere && $openShift->clockInClockPoint)
                <p class="wp-muted">{{ __('time.portal.clock.started_at', ['place' => $openShift->clockInClockPoint->name]) }}</p>
            @endif
            @if ($openElsewhere)
                @if ($clockInPlace !== '')
                    <p class="wp-muted">{{ __('time.portal.clock.started_at', ['place' => $clockInPlace]) }}</p>
                @endif
                <p class="wp-muted">{{ __('time.portal.clock.open_elsewhere_hint') }}</p>
            @endif

            <div class="wp-portal-status">
                @if ($openShift->openBreak)
                    <p>{{ __('time.portal.clock.on_break_since', ['time' => $breakSince ?? '—']) }}</p>
                    <button type="button" class="btn btn--surface btn--sm" wire:click="endBreak">
                        {{ __('time.portal.clock.end_break') }}
                    </button>
                @else
                    <p>{{ __('time.portal.clock.no_break') }}</p>
                    @if ($openElsewhere && ($canPunch ?? false))
                        <button type="button" class="btn btn--primary btn--sm" @click="withGps('transferToThisClockPoint')">
                            {{ __('time.portal.clock.transfer_here') }}
                        </button>
                    @elseif ($canPunch ?? false)
                        <button type="button" class="btn btn--surface btn--sm" wire:click="startBreak">
                            {{ __('time.portal.clock.start_break') }}
                        </button>
                    @elseif (! $openElsewhere)
                        <button type="button" class="btn btn--surface btn--sm" wire:click="startBreak">
                            {{ __('time.portal.clock.start_break') }}
                        </button>
                    @endif
                @endif
            </div>
        @endif
    @endif
</div>
