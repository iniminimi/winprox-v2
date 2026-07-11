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
        'section' => 'present',
        'title' => __('time.presence.present'),
        'subtitle' => __('time.presence.present_subtitle'),
        'shifts' => $snapshot->present,
        'showForceClose' => true,
        'staleHours' => $staleHours,
    ])

    @include('partials.wp-time-presence-section', [
        'section' => 'on_break',
        'title' => __('time.presence.on_break'),
        'subtitle' => __('time.presence.on_break_subtitle'),
        'shifts' => $snapshot->onBreak,
        'showForceClose' => true,
        'staleHours' => $staleHours,
    ])

    @include('partials.wp-time-presence-absent-section', [
        'workers' => $snapshot->notClockedIn,
    ])

    @include('partials.wp-time-force-close-modal')
</div>
