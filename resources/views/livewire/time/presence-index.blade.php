@php
    $presenceView = \App\Enums\TimePresenceViewMode::tryFromRequest($viewMode);
    $presenceStatusFilter = \App\Enums\TimePresenceStatusFilter::tryFromRequest($statusFilter);
@endphp
<div @class(['wp-stack', 'wp-time-presence-page', 'wp-time-presence-page--board' => $presenceView === \App\Enums\TimePresenceViewMode::Board]) wire:poll.visible.30s data-manual-capture="time-presence">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="clock"
                :title="__('time.presence.title')"
                help-page="time.presence"
                :subtitle="__('time.presence.subtitle')"
            />
        </div>
        @can('manualClockIn', \App\Models\WorkShift::class)
            <button type="button" class="btn btn--primary" wire:click="openManualClockIn">
                {{ __('time.manual_clock_in.button') }}
            </button>
        @endcan
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $dashboard->kpis->attention])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    @include('partials.wp-time-presence-kpis', [
        'kpis' => $dashboard->kpis,
        'statusFilter' => $presenceStatusFilter,
    ])

    <div class="wp-card wp-filter-panel wp-time-presence-toolbar">
        <div class="wp-filter-form wp-time-presence-toolbar__form">
            <p class="wp-filter-form__title">{{ __('common.list.filters_title') }}</p>
            <div class="wp-filter-form__row wp-time-presence-toolbar__primary">
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
                            <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
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
                @if (! $dashboard->isSearchMode)
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="presence-view">{{ __('time.presence.view_modes') }}</label>
                        <select id="presence-view" class="wp-select" wire:model.live="viewMode">
                            <option value="board">{{ __('time.presence.view.board') }}</option>
                            <option value="teams">{{ __('time.presence.view.teams') }}</option>
                            <option value="locations">{{ __('time.presence.view.locations') }}</option>
                        </select>
                    </div>
                @endif
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
    @elseif ($presenceView === \App\Enums\TimePresenceViewMode::Board)
        @include('partials.wp-time-presence-board', [
            'teamBuckets' => $dashboard->teamBuckets,
            'attentionItems' => $dashboard->attentionItems,
            'statusFilter' => $presenceStatusFilter,
            'boardLimit' => $boardLimit,
            'teamPageSize' => $teamPageSize,
            'showForceClose' => true,
            'showTeam' => $teamFilter === null,
        ])
    @elseif ($presenceView === \App\Enums\TimePresenceViewMode::Locations)
        @include('partials.wp-time-presence-location-cards', [
            'locationBuckets' => $dashboard->locationBuckets,
        ])
    @else
        @include('partials.wp-time-presence-teams', [
            'teamBuckets' => $dashboard->teamBuckets,
            'expandedTeams' => $expandedTeams,
            'statusFilter' => $presenceStatusFilter,
            'teamShiftLimits' => $teamShiftLimits,
            'teamPageSize' => $teamPageSize,
            'showForceClose' => true,
        ])
    @endif

    @include('partials.wp-time-force-close-modal')
    @include('partials.wp-time-manual-clock-in-modal')
</div>
