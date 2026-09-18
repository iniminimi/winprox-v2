<div class="wp-stack" @if ($canAct ?? false) wire:poll.visible.30s @endif>
    @if ($offerHomescreenShortcut ?? false)
        @push('head')
            <link rel="manifest" href="{{ route('public.time-portal.manifest', $token) }}">
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-title" content="WinProx">
            <link rel="apple-touch-icon" href="{{ asset('images/pwa/winprox-192.png') }}">
            <meta name="theme-color" content="#059669">
        @endpush
    @endif
    <script>
        window.__wpFieldSync = Object.assign(window.__wpFieldSync || {}, {
            identity: @json(($canAct ?? false) && isset($verifiedWorker) && $verifiedWorker ? 'w:'.$verifiedWorker->id : ''),
        });
    </script>
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                @php
                    $tenant = \App\Support\Tenancy::id() ? \App\Models\Tenant::find(\App\Support\Tenancy::id()) : null;
                    $logoUrl = $tenant ? $tenant->logoPublicUrl() : null;
                @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @else
                    <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @endif
            </span>
            <div class="wp-cluster wp-cluster--tight">
                <x-wp-page-help page="portal.time" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        @unless ($canAct ?? false)
            <x-wp-page-head-title variant="portal" icon="clock" :title="__('time.portal.title')">
                <x-slot:toolbar>
                    @if ($offerHomescreenShortcut ?? false)
                        @include('partials.wp-homescreen-shortcut')
                    @endif
                </x-slot:toolbar>
                @if ($showClockPointName ?? false)
                    <p class="wp-muted">{{ $clockPointName }}</p>
                @endif
            </x-wp-page-head-title>
        @endunless
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.inactive.title') }}</h2>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @else
        @php
            $taskHint = null;
            if (($tasks ?? collect())->isNotEmpty()) {
                if (! ($gpsVisits ?? false)) {
                    $taskHint = __('portal.team.read_only_hint');
                } elseif ($openShift !== null && ($openVisitLocationId ?? null) === null) {
                    $taskHint = __('portal.team.complete_needs_visit');
                }
            }
            $hideVisitStartedFlash = ($onSiteGuidance ?? null) !== null
                && $flashMessage === __('time.portal.visit_started');
        @endphp
        @if ($flashMessage !== '' && $flashMessage !== $taskHint && ! $hideVisitStartedFlash)
            <div class="wp-flash">{{ $flashMessage }}</div>
        @endif

        @if ($registerOnly || $showRegisterForm)
            <div class="wp-card wp-card-pad wp-stack" data-manual-capture="portal-team-register">
                <h2 class="wp-section-title">{{ __('portal.team.register.title') }}</h2>
                <p class="wp-muted">{{ $registerOnly ? __('portal.team.register.empty_team_hint') : __('portal.team.register.hint') }}</p>

                <form wire:submit="completeOnboarding" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="reg_first">{{ __('portal.worker.first_name') }}</label>
                        <input id="reg_first" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
                        @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="reg_last">{{ __('portal.worker.last_name') }}</label>
                        <input id="reg_last" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
                        @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.team.register.choose_icon') }}</label>
                        <div class="wp-icon-grid">
                            @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                                <button type="button"
                                        wire:key="reg-icon-{{ $slug }}"
                                        wire:click="$set('selected_icon_slug', '{{ $slug }}')"
                                        @class(['wp-icon-tile', 'is-selected' => $selected_icon_slug === $slug])
                                        title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                        aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                                    <x-wp-worker-icon :slug="$slug" />
                                </button>
                            @endforeach
                        </div>
                        @error('selected_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-stack-tight">
                        <button type="submit" class="btn btn--primary btn--block">{{ __('portal.team.register.submit') }}</button>
                        @unless ($registerOnly)
                            <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="cancelRegistration">{{ __('common.button.cancel') }}</button>
                        @endunless
                    </div>
                </form>
            </div>
        @elseif ($showIdentify)
            <div class="wp-card wp-card-pad wp-stack" data-manual-capture="portal-team-identify">
                <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
                <p class="wp-muted">{{ __('portal.worker.identify_hint') }}</p>
                <form wire:submit="identifyWorker" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="first_name">{{ __('portal.worker.first_name') }}</label>
                        <input id="first_name" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="last_name">{{ __('portal.worker.last_name') }}</label>
                        <input id="last_name" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
                    </div>
                    @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.continue') }}</button>
                </form>
                @if ($allowOpenRegistration)
                    <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="showRegister">{{ __('portal.team.register.cta') }}</button>
                @endif
            </div>
        @elseif ($showPinSetup)
            <div class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-section-title">{{ __('portal.worker.pin_setup_title') }}</h2>
                <p class="wp-muted">
                    {{ __('portal.worker.pin_setup_hint') }}
                    @if ($deviceWorker) <strong>{{ $deviceWorker->displayName() }}</strong>@endif
                </p>
                <form wire:submit="completePinSetup" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="pin_code_setup">{{ __('portal.worker.pin') }}</label>
                        <input id="pin_code_setup" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="4" class="wp-input" wire:model="pin_code">
                        @error('pin_code') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="pin_code_confirm">{{ __('portal.worker.pin_confirm') }}</label>
                        <input id="pin_code_confirm" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="4" class="wp-input" wire:model="pin_code_confirm">
                        @error('pin_code_confirm') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.pin_save') }}</button>
                </form>
            </div>
        @elseif ($showPinVerify)
            <div class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
                <p class="wp-muted">
                    {{ __('portal.worker.pin_verify_hint') }}
                    @if ($deviceWorker) <strong>{{ $deviceWorker->displayName() }}</strong>@endif
                </p>
                <form wire:submit="signInWithPin" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="pin_code_verify">{{ __('portal.worker.pin') }}</label>
                        <input id="pin_code_verify" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="4" class="wp-input" wire:model="pin_code">
                        @error('pin_code') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <p class="wp-hint">{{ __('portal.worker.attempts_left', ['count' => $remainingAttempts]) }}</p>
                    <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.confirm_pin') }}</button>
                </form>
            </div>
        @elseif ($showVerify)
            <div class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
                <p class="wp-muted">
                    {{ __('portal.worker.verify_hint') }}
                    @if ($deviceWorker) <strong>{{ $deviceWorker->displayName() }}</strong>@endif
                </p>
                <div class="wp-icon-grid">
                    @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                        <button type="button"
                                wire:key="verify-icon-{{ $slug }}"
                                wire:click="$set('sign_in_icon_slug', '{{ $slug }}')"
                                @class(['wp-icon-tile', 'is-selected' => $sign_in_icon_slug === $slug])
                                title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                            <x-wp-worker-icon :slug="$slug" />
                        </button>
                    @endforeach
                </div>
                @error('sign_in_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
                <p class="wp-hint">{{ __('portal.worker.attempts_left', ['count' => $remainingAttempts]) }}</p>
                <button type="button" class="btn btn--primary btn--block" wire:click="signInWithIcon" @disabled($sign_in_icon_slug === '')>
                    {{ __('portal.worker.confirm_icon') }}
                </button>
            </div>
        @elseif ($iconBlocked)
            <div class="wp-card wp-card-pad wp-stack">
                <p class="wp-error">{{ __('portal.worker.errors.blocked') }}</p>
            </div>
        @elseif ($showNoWorkers)
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ __('portal.worker.no_workers') }}</p>
            </div>
        @endif

        @if ($canAct)
            <div class="wp-stack" data-manual-capture="portal-team-signed-in">
                @if ($hoursListOpen && $hours !== null)
                    <x-wp-portal-back wire:click="closeHours" />
                    <x-wp-page-head-title variant="portal" icon="clock" :title="__('time.portal.hours.title')">
                        <p class="wp-muted">{{ __('time.portal.hours.subtitle') }}</p>
                    </x-wp-page-head-title>
                    @include('partials.wp-portal-worker-hours', [
                        'hours' => $hours,
                        'hoursMonthLabel' => $hoursMonthLabel,
                        'hoursIsCurrentMonth' => $hoursIsCurrentMonth,
                    ])
                @elseif ($scheduleListOpen && $schedule !== null)
                    <x-wp-portal-back wire:click="closeSchedule" />
                    <x-wp-page-head-title variant="portal" icon="calendar" :title="__('time.portal.schedule.title')">
                        <p class="wp-muted">{{ __('time.portal.schedule.subtitle') }}</p>
                    </x-wp-page-head-title>
                    @include('partials.wp-portal-worker-schedule', [
                        'schedule' => $schedule,
                        'scheduleMonthLabel' => $scheduleMonthLabel,
                    ])
                @elseif ($rosterListOpen && $roster !== null)
                    <x-wp-portal-back wire:click="closeRoster" />
                    <x-wp-page-head-title variant="portal" icon="fire" :title="__('time.roster.title')">
                        <p class="wp-muted">{{ __('time.roster.subtitle') }}</p>
                    </x-wp-page-head-title>
                    @include('partials.wp-time-roster-list', ['roster' => $roster])
                @elseif ($checkingUnit)
                    @if ($skipRoundTaskId)
                        <x-wp-modal closeMethod="closeSkipRoundStop" aria-labelledby="skip-round-title">
                            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                                <div class="wp-modal-head">
                                    <h2 id="skip-round-title" class="wp-section-title">{{ __('portal.round.skip_title') }}</h2>
                                    <x-wp-modal-close wire:click="closeSkipRoundStop" />
                                </div>
                                <p class="wp-muted">{{ __('portal.round.skip_help') }}</p>
                                <div class="wp-field">
                                    <label class="wp-label" for="skipReason">{{ __('portal.round.skip_reason') }}</label>
                                    <textarea id="skipReason" class="wp-textarea" rows="3" wire:model="skipReason" maxlength="500"></textarea>
                                    @error('skipReason') <p class="wp-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="wp-cluster">
                                    <button type="button" class="btn btn--primary" wire:click="submitSkipRoundStop">{{ __('portal.round.skip_confirm') }}</button>
                                    <button type="button" class="btn btn--ghost" wire:click="closeSkipRoundStop">{{ __('common.button.cancel') }}</button>
                                </div>
                            </div>
                        </x-wp-modal>
                    @endif
                    <x-wp-portal-back wire:click="closeClockPointUnitCheck" />
                    <x-wp-page-head-title variant="portal" icon="tasks" :title="__('portal.unit_check.title')" />
                    <p class="wp-muted">{{ $checkingUnit->localizedName() }}</p>
                    @if ($clockPointRoundProgress)
                        @include('partials.wp-portal-round-progress', [
                            'progress' => $clockPointRoundProgress,
                            'currentUnitId' => (int) $checkingUnit->id,
                        ])
                    @endif
                    @if ($clockPointRoundTask && ! $clockPointIsNextStop && ($clockPointRoundProgress['open'] ?? 0) > 0)
                        <p class="wp-muted">{{ __('portal.round.wait_for_next', ['name' => $clockPointRoundProgress['next_unit_name'] ?? '—']) }}</p>
                    @elseif ($checkingUnit->allowsUnitChecks())
                        <div class="wp-card wp-card-pad wp-stack">
                            <p class="wp-muted">{{ __('portal.unit_check.lead') }}</p>
                            @if (($clockPointUnitCheckListItems ?? collect())->isNotEmpty())
                                <div class="wp-stack-tight">
                                    <p class="wp-section-title">{{ __('portal.unit_check.checklist_title') }}</p>
                                    @foreach ($clockPointUnitCheckListItems as $item)
                                        <label class="wp-check" wire:key="cp-check-item-{{ $item->id }}">
                                            <input type="checkbox" value="{{ $item->label }}" wire:model="checkChecklistItems">
                                            <span>{{ $clockPointUnitCheckList?->localizedItemLabel($item->label) ?? $item->label }}</span>
                                        </label>
                                    @endforeach
                                    @error('checkChecklistItems') <p class="wp-error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            @include('partials.wp-portal-unit-check-evidence', [
                                'descriptionId' => 'cp-check-description',
                                'preferCamera' => true,
                                'storeLocal' => false,
                            ])
                            <div class="wp-cluster wp-cluster--wrap">
                                <button type="button" class="btn btn--primary" wire:click="submitClockPointUnitCheck('ok')">
                                    {{ __('portal.unit_check.ok') }}
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="submitClockPointUnitCheck('not_ok')">
                                    {{ __('portal.unit_check.not_ok') }}
                                </button>
                            </div>
                            @error('checkResult') <p class="wp-error">{{ $message }}</p> @enderror
                            @error('checkLatitude') <p class="wp-error">{{ $message }}</p> @enderror
                            @error('checkLongitude') <p class="wp-error">{{ $message }}</p> @enderror
                            @error('checkCheckedAt') <p class="wp-error">{{ $message }}</p> @enderror
                            @if ($clockPointIsNextStop && $clockPointRoundTask)
                                <p class="wp-muted wp-text-sm">{{ __('portal.round.do_check_here') }}</p>
                                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="openSkipRoundStop({{ $clockPointRoundTask->id }})">
                                    {{ __('portal.round.skip_stop') }}
                                </button>
                            @endif
                        </div>
                    @else
                        <p class="wp-muted">{{ __('portal.worker.errors.no_permission') }}</p>
                    @endif
                @else
                    <div class="wp-portal-worker-bar">
                        <div class="wp-card wp-card-pad wp-cluster">
                            <strong class="wp-text-body">{{ __('common.welcome') }} {{ $verifiedWorker?->displayName() }}</strong>
                        </div>
                        <div class="wp-portal-worker-actions">
                            @include('partials.wp-portal-sign-out', ['signOutMethod' => 'signOut'])
                        </div>
                    </div>

                    @if ($onSiteGuidance)
                        <div class="wp-flash">
                            @if (! $onSiteGuidance['remaining_here'] && filled($onSiteGuidance['next']))
                                {{ __('portal.team.on_site_here_go_next', ['here' => $onSiteGuidance['here'], 'next' => $onSiteGuidance['next']]) }}
                            @elseif ($onSiteGuidance['remaining_here'])
                                {{ __('portal.team.on_site_here_work', ['here' => $onSiteGuidance['here']]) }}
                                @if (filled($onSiteGuidance['next']))
                                    {{ __('portal.team.on_site_here_then', ['next' => $onSiteGuidance['next']]) }}
                                @endif
                            @else
                                {{ __('portal.team.on_site_here_done', ['here' => $onSiteGuidance['here']]) }}
                            @endif
                        </div>
                    @endif

                    @if ($verifiedWorker?->is_teamleader)
                        @include('partials.wp-portal-teamleader-release')
                    @endif

                    @if ($hasTimeModule)
                    <div
                        class="wp-stack"
                        x-data="{
                            gpsOn: @js($gpsOnClock ?? false),
                            gpsVisits: @js($gpsVisits ?? false),
                            hereLat: null,
                            hereLng: null,
                            openVisitUnitId: @js($openVisitUnitId),
                            openVisitLocationId: @js($openVisitLocationId ?? null),
                            async withGps(method) {
                                const run = () => $wire[method]();
                                if (!this.gpsOn || !navigator.geolocation) {
                                    run();
                                    return;
                                }
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        $wire.clockGpsLatitude = String(pos.coords.latitude);
                                        $wire.clockGpsLongitude = String(pos.coords.longitude);
                                        run();
                                    },
                                    () => run(),
                                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
                                );
                            },
                            async withFreshGps(method, arg, extra) {
                                const call = (lat, lng) => {
                                    if (arg === undefined) {
                                        $wire[method](lat, lng);
                                    } else if (extra === undefined) {
                                        $wire[method](arg, lat, lng);
                                    } else {
                                        $wire[method](arg, lat, lng, extra);
                                    }
                                };
                                if (!navigator.geolocation) {
                                    call(null, null);
                                    return;
                                }
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        this.hereLat = pos.coords.latitude;
                                        this.hereLng = pos.coords.longitude;
                                        call(pos.coords.latitude, pos.coords.longitude);
                                    },
                                    () => call(null, null),
                                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                                );
                            },
                            metersBetween(latA, lngA, latB, lngB) {
                                const earth = 6371000;
                                const dLat = (latB - latA) * Math.PI / 180;
                                const dLng = (lngB - lngA) * Math.PI / 180;
                                const a = Math.sin(dLat / 2) ** 2
                                    + Math.cos(latA * Math.PI / 180) * Math.cos(latB * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
                                return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
                            },
                            inRange(dest) {
                                if (!dest.can_start || dest.pin_latitude === null || dest.pin_longitude === null) {
                                    return false;
                                }
                                if (this.hereLat === null || this.hereLng === null) {
                                    return false;
                                }
                                if (this.openVisitUnitId && Number(this.openVisitUnitId) === Number(dest.unit_id)) {
                                    return false;
                                }
                                if (this.openVisitLocationId && Number(this.openVisitLocationId) === Number(dest.location_id)) {
                                    return false;
                                }
                                return this.metersBetween(this.hereLat, this.hereLng, dest.pin_latitude, dest.pin_longitude) <= dest.radius_meters;
                            },
                            inLocationRange(group) {
                                if (!group.location_can_start || group.location_pin_latitude === null || group.location_pin_longitude === null) {
                                    return false;
                                }
                                if (this.hereLat === null || this.hereLng === null) {
                                    return false;
                                }
                                if (this.openVisitLocationId && Number(this.openVisitLocationId) === Number(group.location_id)) {
                                    return false;
                                }
                                return this.metersBetween(this.hereLat, this.hereLng, group.location_pin_latitude, group.location_pin_longitude) <= group.radius_meters;
                            },
                            refreshHere() {
                                if (!navigator.geolocation) {
                                    return;
                                }
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        this.hereLat = pos.coords.latitude;
                                        this.hereLng = pos.coords.longitude;
                                    },
                                    () => {},
                                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                                );
                            }
                        }"
                        x-init="refreshHere(); window.addEventListener('pageshow', () => refreshHere())"
                    >
                        <div class="wp-card wp-card-pad wp-stack">
                        <h2 class="wp-section-title">{{ __('time.portal.clock.title') }}</h2>
                        @if ($openShift === null)
                            <p class="wp-muted">{{ __('time.portal.clock.not_clocked_in') }}</p>
                            @if ($canPunch ?? false)
                                <button type="button" class="btn btn--primary btn--block" @click="withGps('clockIn')">
                                    {{ __('time.portal.clock.in') }}
                                </button>
                            @else
                                <p class="wp-muted">{{ __('time.portal.clock.scan_required_hint') }}</p>
                            @endif
                        @else
                            @php
                                $presencePoint = $openShift->currentClockPoint();
                                $clockInPlace = $presencePoint?->location?->name
                                    ? $presencePoint->location->name.($presencePoint->name ? ' · '.$presencePoint->name : '')
                                    : ($presencePoint?->name ?? '');
                                $openElsewhere = $presencePoint !== null
                                    && (int) $presencePoint->id !== (int) $clockPointId;
                                $startedElsewhere = $openShift->hasLocationHops()
                                    && (int) $openShift->clock_in_clock_point_id !== (int) ($presencePoint?->id ?? 0);
                            @endphp
                            <p class="wp-muted">
                                @if ($clockInPlace !== '')
                                    {{ __('time.portal.clock.clocked_in_since_at', ['time' => $openShift->clock_in_at->format('H:i'), 'place' => $clockInPlace]) }}
                                @else
                                    {{ __('time.portal.clock.clocked_in_since', ['time' => $openShift->clock_in_at->format('H:i')]) }}
                                @endif
                            </p>
                            @if ($openShift->isManuallyClockedIn())
                                <p class="wp-muted">{{ __('time.manual_clock_in.badge') }}</p>
                            @endif
                            @if (($gpsVisits ?? false) && $openShift->openVisit)
                                <p class="wp-muted">{{ __('time.portal.clock.working_at', ['place' => trim(($openShift->openVisit->location?->name ?? '').' · '.($openShift->openVisit->unit?->name ?? ''), ' ·')]) }}</p>
                                <button type="button" class="btn btn--primary btn--block" @click="withFreshGps('endWorkVisit')">
                                    {{ __('time.portal.clock.stop_work') }}
                                </button>
                            @endif
                            @if ($startedElsewhere && $openShift->clockInClockPoint)
                                <p class="wp-muted">{{ __('time.portal.clock.started_at', ['place' => $openShift->clockInClockPoint->name]) }}</p>
                            @endif
                            @if ($openElsewhere)
                                <p class="wp-muted">{{ __('time.portal.clock.open_elsewhere_hint') }}</p>
                            @endif
                            @if ($openShift->openBreak)
                                <p class="wp-muted">{{ __('time.portal.clock.on_break_since', ['time' => $openShift->openBreak->started_at->format('H:i')]) }}</p>
                                <button type="button" class="btn btn--primary btn--block" wire:click="endBreak">
                                    {{ __('time.portal.clock.end_break') }}
                                </button>
                            @elseif ($canPunch ?? false)
                                <div class="wp-cluster">
                                    @if ($openElsewhere)
                                        <button type="button" class="btn btn--primary" @click="withGps('transferToThisClockPoint')">
                                            {{ __('time.portal.clock.transfer_here') }}
                                        </button>
                                    @else
                                        <button type="button" class="btn btn--surface" wire:click="startBreak">
                                            {{ __('time.portal.clock.start_break') }}
                                        </button>
                                    @endif
                                    <button type="button" @class(['btn', 'btn--surface' => $openElsewhere, 'btn--primary' => ! $openElsewhere]) @click="withGps('clockOut')">
                                        {{ __('time.portal.clock.out') }}
                                    </button>
                                </div>
                            @else
                                @unless ($openElsewhere)
                                    <button type="button" class="btn btn--surface btn--block" wire:click="startBreak">
                                        {{ __('time.portal.clock.start_break') }}
                                    </button>
                                @endunless
                                <p class="wp-muted">{{ __('time.portal.clock.scan_required_hint') }}</p>
                            @endif
                        @endif
                        </div>

                        @if ($openShift !== null && ($gpsVisits ?? false))
                            <div class="wp-card wp-card-pad wp-stack" data-manual-capture="time-today">
                                <h2 class="wp-section-title">{{ __('time.portal.today.title') }}</h2>
                                @forelse ($todayDestinations as $group)
                                    @php
                                        $placeLabel = $group['location_name'];
                                        if ($group['address_line'] !== '') {
                                            $placeLabel .= ', '.$group['address_line'];
                                        }
                                    @endphp
                                    <div class="wp-today-destination" wire:key="today-loc-{{ $group['location_id'] }}">
                                        <div class="wp-today-destination__head">
                                            @if ($group['location_maps_url'])
                                                <a
                                                    class="wp-today-destination__place wp-today-destination__nav"
                                                    href="{{ $group['location_maps_url'] }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    aria-label="{{ __('time.portal.today.navigate') }}"
                                                >
                                                    <span>{{ $placeLabel }}</span>
                                                    @include('partials.wp-gps-pin-icon', ['class' => 'wp-today-destination__pin'])
                                                </a>
                                            @else
                                                <p class="wp-today-destination__place">{{ $placeLabel }}</p>
                                            @endif
                                            <template x-if="inLocationRange(@js($group))">
                                                <button type="button" class="btn btn--surface" @click="withFreshGps('startWorkVisit', 0, {{ (int) $group['location_id'] }})">
                                                    {{ __('time.portal.today.start_work') }}
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @empty
                                    <p class="wp-muted">{{ __('time.portal.today.empty') }}</p>
                                @endforelse
                                @unless ($openShift->openVisit)
                                    <button type="button" class="btn btn--surface btn--block" @click="withFreshGps('refreshNearbyClockUnits')">
                                        {{ __('time.portal.clock.find_nearby') }}
                                    </button>
                                    @forelse ($nearbyClockUnits as $nearby)
                                        @php
                                            $nearbyPlace = trim($nearby['location_name'].((string) ($nearby['unit_name'] ?? '') !== '' ? ' · '.$nearby['unit_name'] : ''), ' · ');
                                            $nearbyUnitId = (int) ($nearby['unit_id'] ?? 0);
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn btn--surface btn--block"
                                            @click="withFreshGps('startWorkVisit', {{ $nearbyUnitId }}{{ $nearbyUnitId > 0 ? '' : ', '.(int) $nearby['location_id'] }})"
                                        >
                                            {{ __('time.portal.clock.start_work_at', ['place' => $nearbyPlace, 'distance' => $nearby['distance_meters']]) }}
                                        </button>
                                    @empty
                                        @if ($nearbyClockUnitsLoaded)
                                            <p class="wp-muted">{{ __('time.portal.clock.no_nearby_units') }}</p>
                                        @endif
                                    @endforelse
                                @endunless
                            </div>
                        @endif
                    </div>
                    @endif

                    @if ($tasks->isNotEmpty())
                        @if ($taskHint !== null)
                            <div class="wp-flash wp-flash--muted">{{ $taskHint }}</div>
                        @endif

                        <x-wp-page-head-title variant="portal" icon="tasks" :title="__('portal.worker.open_tasks')" />
                        <div class="wp-list">
                            @foreach ($tasks as $task)
                                @php
                                    $isRound = $task->issue?->isInspectionRound() ?? false;
                                    $roundProgress = $isRound ? app(\App\Actions\Tasks\RoundTaskCompletionAction::class)->progress($task) : null;
                                    $nextStopUnitId = $isRound ? ($roundProgress['next_unit_id'] ?? null) : null;
                                    $taskLocationId = $isRound
                                        ? ($task->issue?->roundStops
                                            ?->firstWhere('unit_id', $nextStopUnitId)
                                            ?->unit
                                            ?->location_id)
                                        : ($task->issue?->location_id ?? $task->issue?->unit?->location_id);
                                    $onSite = ($gpsVisits ?? false) && ($openVisitLocationId ?? null) !== null && $taskLocationId !== null && (int) $taskLocationId === (int) $openVisitLocationId;
                                @endphp
                                <div class="wp-card wp-card-pad wp-stack" wire:key="time-task-{{ $task->id }}">
                                    @if ($isRound)
                                        @if ($task->issue?->isApproved())
                                            <p class="wp-text-body">{{ $task->displayDescription() }}</p>
                                        @endif
                                        <div class="wp-cluster">
                                            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                                {{ $task->priority->label() }}
                                            </span>
                                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                                        </div>
                                        @if ($roundProgress['next_unit_name'] ?? null)
                                            <p class="wp-muted">{{ __('portal.round.next_stop', ['name' => $roundProgress['next_unit_name']]) }}</p>
                                        @endif
                                        @if ($onSite && $nextStopUnitId)
                                            <button type="button" class="btn btn--primary btn--block" wire:click="openClockPointUnitCheck({{ (int) $nextStopUnitId }})">
                                                {{ __('time.portal.today.do_check') }}
                                            </button>
                                        @endif
                                    @else
                                        <div class="wp-cluster">
                                            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                                {{ $task->priority->label() }}
                                            </span>
                                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                                            @if ($task->issue?->location)
                                                <span class="wp-muted">{{ $task->issue->location->localizedName() }}@if ($task->issue->unit) &middot; {{ $task->issue->unit->localizedName() }}@endif</span>
                                            @endif
                                        </div>
                                        @if ($task->issue?->isApproved())
                                            <p class="wp-text-body">{{ $task->displayDescription() }}</p>
                                        @endif

                                        @include('partials.wp-portal-issue-photos', [
                                            'issue' => $task->issue,
                                            'wireKeyPrefix' => 'tp-'.$task->id,
                                            'forWorker' => true,
                                        ])

                                        @if ($completingTaskId === $task->id)
                                            <form
                                                class="wp-stack"
                                                x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
                                                @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.submitCompleteTask()"
                                            >
                                                @include('partials.wp-portal-esg-measurement', ['task' => $task])
                                                <div class="wp-field">
                                                    <label class="wp-label" for="note-{{ $task->id }}">{{ __('portal.worker.note') }}</label>
                                                    <textarea id="note-{{ $task->id }}" class="wp-textarea" rows="3"
                                                              wire:model="completingNote"
                                                              placeholder="{{ __('portal.worker.note_placeholder') }}"></textarea>
                                                    @error('completingNote') <p class="wp-error">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="wp-field">
                                                    <label class="wp-label">{{ __('portal.worker.photos') }}</label>
                                                    @include('partials.wp-issue-photo-upload', ['model' => 'completingPhotos', 'preferCamera' => true, 'storeLocal' => false])
                                                    @error('completingPhotos') <p class="wp-error">{{ $message }}</p> @enderror
                                                    @error('completingPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="wp-stack-tight">
                                                    <button type="submit" class="btn btn--primary btn--block">
                                                        {{ __('portal.worker.confirm_complete') }}
                                                    </button>
                                                    <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="cancelCompleteTask">{{ __('common.button.cancel') }}</button>
                                                </div>
                                            </form>
                                        @elseif ($onSite)
                                            <div class="wp-stack-tight">
                                                @if ($task->canStart())
                                                    <button type="button" class="btn btn--warning btn--block" wire:click="startTask({{ $task->id }})">
                                                        {{ __('portal.worker.start_task') }}
                                                    </button>
                                                @endif
                                                @if ($task->canComplete())
                                                    <button type="button" class="btn btn--primary btn--block" wire:click="beginCompleteTask({{ $task->id }})">
                                                        {{ __('portal.worker.complete_task') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif (($gpsVisits ?? false) && ($openVisitLocationId ?? null) !== null)
                                            <p class="wp-muted wp-text-sm">{{ __('portal.worker.errors.not_this_location') }}</p>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($hasTimeModule)
                        <div class="wp-tiles">
                            <button type="button" class="wp-tile" wire:click="openSchedule">
                                <span class="wp-cluster">
                                    <x-wp-icon name="calendar" class="wp-tile-icon" />
                                    <span class="wp-tile-title">{{ __('time.portal.schedule.tile') }}</span>
                                    @if (($scheduleUnreadCount ?? 0) > 0)
                                        <span class="wp-pill wp-pill--new">{{ $scheduleUnreadCount }}</span>
                                    @endif
                                </span>
                                <span class="wp-tile-sub">{{ __('time.portal.schedule.tile_sub') }}</span>
                            </button>
                            <button type="button" class="wp-tile" wire:click="openHours">
                                <span class="wp-cluster">
                                    <x-wp-icon name="clock" class="wp-tile-icon" />
                                    <span class="wp-tile-title">{{ __('time.portal.hours.tile') }}</span>
                                </span>
                                <span class="wp-tile-sub">{{ __('time.portal.hours.tile_sub') }}</span>
                            </button>
                            @if ($evacuationList)
                                <button type="button" class="wp-tile" wire:click="openRoster">
                                    <span class="wp-cluster">
                                        <x-wp-icon name="fire" class="wp-tile-icon" />
                                        <span class="wp-tile-title">{{ __('time.roster.tile') }}</span>
                                    </span>
                                    <span class="wp-tile-sub">{{ __('time.roster.tile_sub') }}</span>
                                </button>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            @if ($rosterAckOpen)
                <x-wp-modal closeMethod="closeRoster" aria-labelledby="time-roster-ack-title">
                    <form wire:submit="acknowledgeRoster" class="wp-card wp-card-pad wp-stack wp-modal-card">
                        <div class="wp-modal-head">
                            <h2 id="time-roster-ack-title" class="wp-h2">{{ __('time.roster.ack_title') }}</h2>
                            <x-wp-modal-close wire:click="closeRoster" />
                        </div>
                        <p class="wp-muted">{{ __('time.roster.ack_intro') }}</p>
                        <label class="wp-check">
                            <input type="checkbox" wire:model="rosterAcknowledged">
                            <span>{{ __('time.roster.ack_label') }}</span>
                        </label>
                        @error('rosterAcknowledged') <p class="wp-error">{{ $message }}</p> @enderror
                        <div class="wp-cluster">
                            <button type="button" class="btn btn--surface" wire:click="closeRoster">{{ __('common.button.cancel') }}</button>
                            <button type="submit" class="btn btn--primary">{{ __('time.roster.ack_submit') }}</button>
                        </div>
                    </form>
                </x-wp-modal>
            @endif
        @endif
    @endif

    @if (($offerHomescreenShortcut ?? false) && $homescreenHelpOpen)
        <x-wp-modal closeMethod="closeHomescreenHelp" aria-labelledby="homescreen-help-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="homescreen-help-title" class="wp-h2">{{ __('time.portal.homescreen.help_title') }}</h2>
                    <x-wp-modal-close wire:click="closeHomescreenHelp" />
                </div>
                <p>{{ __('time.portal.homescreen.help_intro') }}</p>
                <p class="wp-muted">{{ __('time.portal.homescreen.help_android') }}</p>
                <p class="wp-muted">{{ __('time.portal.homescreen.help_ios') }}</p>
                <p class="wp-muted">{{ __('time.portal.homescreen.help_existing') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--primary" wire:click="closeHomescreenHelp">{{ __('common.button.close') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
