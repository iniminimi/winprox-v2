<div class="wp-stack" wire:poll.visible.30s data-manual-capture="time-presence">
    @php
        $presenceView = \App\Enums\TimePresenceViewMode::tryFromRequest($viewMode);
    @endphp
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.presence"
        :subtitle="__('time.presence.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $dashboard->kpis->attention])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    @include('partials.wp-time-presence-kpis', [
        'kpis' => $dashboard->kpis,
        'statusFilter' => $statusFilter,
    ])

    <div class="wp-card wp-filter-panel wp-time-presence-toolbar">
        <div class="wp-filter-form wp-time-presence-toolbar__form">
            <div class="wp-filter-form__row wp-time-presence-toolbar__quad">
                <div class="wp-filter-cell wp-filter-cell--search">
                    <label class="wp-filter-inline-label" for="presence-search">{{ __('time.presence.search_label') }}</label>
                    <input id="presence-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                           placeholder="{{ __('time.presence.search_placeholder') }}">
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="presence-team">{{ __('time.filters.team') }}</label>
                    <select id="presence-team" class="wp-select" wire:model.live="teamFilter">
                        <option value="">{{ __('time.filters.all_teams') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="presence-location">{{ __('time.presence.location') }}</label>
                    <select id="presence-location" class="wp-select" wire:model.live="locationFilter">
                        <option value="">{{ __('time.presence.all_locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="presence-clock-point">{{ __('time.filters.clock_point') }}</label>
                    <select id="presence-clock-point" class="wp-select" wire:model.live="clockPointFilter">
                        <option value="">{{ __('time.filters.all_clock_points') }}</option>
                        @foreach ($clockPoints as $clockPoint)
                            <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="wp-time-presence-toolbar__meta">
                <div class="wp-time-presence-toolbar__actions">
                    @include('partials.wp-time-presence-status-filters', ['statusFilter' => $statusFilter])

                    @if (! $dashboard->isSearchMode)
                        @include('partials.wp-time-presence-view-toggle', ['presenceView' => $presenceView])
                    @endif
                </div>
                <p class="wp-time-presence-toolbar__updated wp-muted">
                    <x-wp-icon name="clock" class="wp-time-presence-toolbar__updated-icon" />
                    <span>{{ now()->format('H:i') }}</span>
                </p>
            </div>
        </div>
    </div>

    @if ($dashboard->isSearchMode)
        @include('partials.wp-time-presence-search-results', [
            'shifts' => $dashboard->searchShifts,
            'absentWorkers' => $dashboard->searchAbsentWorkers,
            'showForceClose' => true,
            'staleHours' => $staleHours,
        ])
    @elseif ($presenceView === \App\Enums\TimePresenceViewMode::Cards)
        @include('partials.wp-time-presence-team-cards', [
            'teamBuckets' => $dashboard->teamBuckets,
            'statusFilter' => $statusFilter,
        ])
    @elseif ($presenceView === \App\Enums\TimePresenceViewMode::Locations)
        @include('partials.wp-time-presence-location-cards', [
            'locationBuckets' => $dashboard->locationBuckets,
        ])
    @else
        @include('partials.wp-time-presence-teams', [
            'teamBuckets' => $dashboard->teamBuckets,
            'expandedTeams' => $expandedTeams,
            'statusFilter' => $statusFilter,
            'teamShiftLimits' => $teamShiftLimits,
            'teamPageSize' => $teamPageSize,
            'showForceClose' => true,
        ])
    @endif

    @include('partials.wp-time-force-close-modal')
</div>
