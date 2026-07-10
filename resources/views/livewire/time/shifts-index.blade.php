<div class="wp-stack">
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.shifts"
        :subtitle="__('time.shifts.subtitle')"
    />

    @include('partials.wp-time-nav')

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-grid wp-grid--3">
            <div class="wp-field">
                <label class="wp-label" for="shifts-from">{{ __('time.filters.from') }}</label>
                <input id="shifts-from" type="date" class="wp-input" wire:model="from">
            </div>
            <div class="wp-field">
                <label class="wp-label" for="shifts-to">{{ __('time.filters.to') }}</label>
                <input id="shifts-to" type="date" class="wp-input" wire:model="to">
            </div>
            <div class="wp-field">
                <label class="wp-label" for="shifts-team">{{ __('time.filters.team') }}</label>
                <select id="shifts-team" class="wp-input" wire:model="teamFilter">
                    <option value="">{{ __('time.filters.all_teams') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-field">
                <label class="wp-label" for="shifts-worker">{{ __('time.filters.worker') }}</label>
                <select id="shifts-worker" class="wp-input" wire:model="workerFilter">
                    <option value="">{{ __('time.filters.all_workers') }}</option>
                    @foreach ($workers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-field">
                <label class="wp-label" for="shifts-clock-point">{{ __('time.filters.clock_point') }}</label>
                <select id="shifts-clock-point" class="wp-input" wire:model="clockPointFilter">
                    <option value="">{{ __('time.filters.all_clock_points') }}</option>
                    @foreach ($clockPoints as $clockPoint)
                        <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary" wire:click="applyFilters">{{ __('time.filters.apply') }}</button>
            <a href="{{ $exportUrl }}" class="btn btn--surface">{{ __('time.export.button') }}</a>
        </div>
    </div>

    <div class="wp-list">
        @forelse ($shifts as $shift)
            <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="shift-{{ $shift->id }}">
                <div>
                    <strong>{{ $shift->worker?->displayName() }}</strong>
                    <p class="wp-muted wp-text-sm">
                        {{ $shift->clock_in_at->format('d-m-Y') }}
                        &middot; {{ $shift->clock_in_at->format('H:i') }}
                        @if ($shift->clock_out_at)
                            – {{ $shift->clock_out_at->format('H:i') }}
                        @endif
                        &middot; {{ $shift->team?->name }}
                    </p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('time.shifts.break_minutes', ['minutes' => $shift->total_break_minutes]) }}
                        &middot; {{ __('time.shifts.net_minutes', ['minutes' => $shift->netWorkMinutes()]) }}
                    </p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('time.shifts.clocked_in_at', ['name' => $shift->clockInClockPoint?->name ?? '—']) }}
                        @if ($shift->clockOutClockPoint)
                            &middot; {{ __('time.shifts.clocked_out_at', ['name' => $shift->clockOutClockPoint->name]) }}
                        @endif
                    </p>
                </div>
                <div class="wp-cluster">
                    @if ($shift->status === \App\Enums\WorkShiftStatus::ForceClosed && $shift->clock_out_source === \App\Enums\ClockSource::Auto)
                        <span class="wp-pill wp-pill--closed">{{ __('time.status.auto_closed') }}</span>
                    @else
                        <span class="wp-pill">{{ __('time.status.'.$shift->status->value) }}</span>
                    @endif
                    @if ($shift->status->isOpen())
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="forceClose({{ $shift->id }})" wire:confirm="{{ __('time.presence.force_close_confirm') }}">
                            {{ __('time.presence.force_close') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('time.shifts.empty') }}</p></div>
        @endforelse
    </div>

    {{ $shifts->links() }}
</div>
