<div class="wp-stack" @if ($canAct ?? false) wire:poll.visible.30s @endif>
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
        <x-wp-page-head-title variant="portal" icon="clock" :title="__('time.portal.title')">
            @if ($showClockPointName ?? false)
                <p class="wp-muted">{{ $clockPointName }}</p>
            @endif
        </x-wp-page-head-title>
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.inactive.title') }}</h2>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @else
        @if ($flashMessage !== '')
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
                    <button type="button" class="btn btn--ghost btn--block btn--sm" onclick="window.wpFieldFlush?.()" wire:click="signInAsDifferentWorker">
                        {{ __('portal.worker.different_worker') }}
                    </button>
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
                    <button type="button" class="btn btn--ghost btn--block btn--sm" onclick="window.wpFieldFlush?.()" wire:click="signInAsDifferentWorker">
                        {{ __('portal.worker.different_worker') }}
                    </button>
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
                <button type="button" class="btn btn--ghost btn--block btn--sm" onclick="window.wpFieldFlush?.()" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
            </div>
        @elseif ($iconBlocked)
            <div class="wp-card wp-card-pad wp-stack">
                <p class="wp-error">{{ __('portal.worker.errors.blocked') }}</p>
                <button type="button" class="btn btn--ghost btn--block btn--sm" onclick="window.wpFieldFlush?.()" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
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
                @else
                    <div class="wp-card wp-card-pad wp-cluster">
                        <strong class="wp-text-body">{{ __('common.welcome') }} {{ $verifiedWorker?->displayName() }}</strong>
                    </div>

                    <div class="wp-portal-worker-actions">
                        @include('partials.wp-portal-sign-out')
                    </div>

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
                            async withFreshGps(method, arg) {
                                const call = (lat, lng) => {
                                    if (arg === undefined) {
                                        $wire[method](lat, lng);
                                    } else {
                                        $wire[method](arg, lat, lng);
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
                                return this.metersBetween(this.hereLat, this.hereLng, dest.pin_latitude, dest.pin_longitude) <= dest.radius_meters;
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
                                <p class="wp-muted">{{ __('time.portal.today.subtitle') }}</p>
                                @forelse ($todayDestinations as $destination)
                                    <div class="wp-stack" wire:key="today-dest-{{ $destination['key'] }}">
                                        <strong class="wp-text-body">{{ $destination['location_name'] }}</strong>
                                        @if ($destination['unit_name'])
                                            <p class="wp-text-body">{{ $destination['unit_name'] }}</p>
                                        @endif
                                        @if ($destination['address_line'] !== '')
                                            <p class="wp-muted">{{ $destination['address_line'] }}</p>
                                        @endif
                                        <a class="btn btn--primary btn--block" href="{{ $destination['maps_url'] }}" target="_blank" rel="noopener noreferrer">
                                            {{ __('time.portal.today.navigate') }}
                                        </a>
                                        <template x-if="inRange(@js($destination))">
                                            <button type="button" class="btn btn--surface btn--block" @click="withFreshGps('startWorkVisit', {{ (int) ($destination['unit_id'] ?? 0) }})">
                                                {{ __('time.portal.today.start_work') }}
                                            </button>
                                        </template>
                                    </div>
                                @empty
                                    <p class="wp-muted">{{ __('time.portal.today.empty') }}</p>
                                @endforelse
                                @unless ($openShift->openVisit)
                                    <button type="button" class="btn btn--surface btn--block" @click="withFreshGps('refreshNearbyClockUnits')">
                                        {{ __('time.portal.clock.find_nearby') }}
                                    </button>
                                    @forelse ($nearbyClockUnits as $nearby)
                                        <button type="button" class="btn btn--surface btn--block" @click="withFreshGps('startWorkVisit', {{ (int) $nearby['unit_id'] }})">
                                            {{ __('time.portal.clock.start_work_at', ['place' => trim($nearby['location_name'].' · '.$nearby['unit_name'], ' · '), 'distance' => $nearby['distance_meters']]) }}
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
                        <div class="wp-flash wp-flash--muted">{{ __('portal.team.read_only_hint') }}</div>

                        <x-wp-page-head-title variant="portal" icon="tasks" :title="__('portal.worker.open_tasks')" />
                        <div class="wp-list">
                            @foreach ($tasks as $task)
                                <div class="wp-card wp-card-pad wp-stack" wire:key="time-task-{{ $task->id }}">
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
</div>
