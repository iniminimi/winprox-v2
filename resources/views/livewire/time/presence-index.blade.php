<div class="wp-stack" wire:poll.30s data-manual-capture="time-presence">
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.presence"
        :subtitle="__('time.presence.subtitle')"
    />

    @include('partials.wp-time-nav')

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-grid wp-grid--3">
            <div class="wp-field">
                <label class="wp-label" for="presence-team">{{ __('time.filters.team') }}</label>
                <select id="presence-team" class="wp-input" wire:model.live="teamFilter">
                    <option value="">{{ __('time.filters.all_teams') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-field">
                <label class="wp-label" for="presence-clock-point">{{ __('time.filters.clock_point') }}</label>
                <select id="presence-clock-point" class="wp-input" wire:model.live="clockPointFilter">
                    <option value="">{{ __('time.filters.all_clock_points') }}</option>
                    @foreach ($clockPoints as $clockPoint)
                        <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-field">
                <label class="wp-label" for="presence-search">{{ __('time.filters.search') }}</label>
                <input id="presence-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search">
            </div>
        </div>
    </div>

    @include('partials.wp-time-presence-section', [
        'title' => __('time.presence.present'),
        'shifts' => $snapshot->present,
        'showForceClose' => true,
        'staleHours' => $staleHours,
    ])

    @include('partials.wp-time-presence-section', [
        'title' => __('time.presence.on_break'),
        'shifts' => $snapshot->onBreak,
        'showForceClose' => true,
        'staleHours' => $staleHours,
    ])

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('time.presence.not_clocked_in') }} ({{ $snapshot->notClockedIn->count() }})</h2>
        @forelse ($snapshot->notClockedIn as $worker)
            <div class="wp-cluster" wire:key="not-clocked-{{ $worker->id }}">
                <span>{{ $worker->displayName() }}</span>
                <span class="wp-muted">{{ $worker->team?->name }}</span>
            </div>
        @empty
            <p class="wp-muted">{{ __('time.presence.empty_not_clocked_in') }}</p>
        @endforelse
    </div>

    @include('partials.wp-time-force-close-modal')
</div>
