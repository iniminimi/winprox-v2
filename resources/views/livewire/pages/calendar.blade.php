<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('calendar.title') }}</h1>
            <p class="wp-muted">{{ __('calendar.subtitle') }}</p>
        </div>
        <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('calendar.briefing') }}</a>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-row">
            <div class="wp-chip-row">
                @foreach (['month', 'week', 'day'] as $mode)
                    <button type="button"
                            class="btn btn--ghost btn--sm {{ $viewMode === $mode ? 'is-active' : '' }}"
                            wire:click="setViewMode('{{ $mode }}')">
                        {{ __('calendar.view.'.$mode) }}
                    </button>
                @endforeach
            </div>
            <div class="wp-chip-row">
                <button type="button"
                        class="btn btn--ghost btn--sm {{ $entryType === 'tasks' ? 'is-active' : '' }}"
                        wire:click="setEntryType('tasks')">
                    {{ __('calendar.type.tasks') }}
                </button>
                <button type="button"
                        class="btn btn--ghost btn--sm {{ $entryType === 'issues' ? 'is-active' : '' }}"
                        wire:click="setEntryType('issues')">
                    {{ __('calendar.type.issues') }}
                </button>
            </div>
        </div>

        <div class="wp-row">
            <div class="wp-cluster">
                <button type="button" class="btn btn--ghost btn--sm" wire:click="previousPeriod">‹</button>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="goToToday">{{ __('calendar.today') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="nextPeriod">›</button>
            </div>
            <strong>{{ $periodLabel }}</strong>
            <select class="wp-select wp-select--compact" wire:model.live="locationFilter">
                <option value="">{{ __('calendar.all_locations') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($isMonthView)
        <div class="wp-calendar">
            <div class="wp-calendar-head">
                @foreach ($weekDayLabels as $label)
                    <div class="wp-calendar-head-cell">{{ $label }}</div>
                @endforeach
            </div>
            <div class="wp-calendar-grid">
                @foreach ($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $entries = $entriesByDate->get($dateKey, collect());
                        $isToday = $day->isToday();
                        $isOtherMonth = ! $day->isSameMonth($currentMonth);
                    @endphp
                    <div class="wp-calendar-cell {{ $isOtherMonth ? 'wp-calendar-cell--muted' : '' }} {{ $isToday ? 'wp-calendar-cell--today' : '' }}"
                         wire:key="cal-{{ $dateKey }}">
                        <div class="wp-calendar-day-num">{{ $day->day }}</div>
                        <div class="wp-calendar-entries">
                            @foreach ($entries as $entry)
                                @if ($entryType === 'issues')
                                    <a href="{{ route('issues.show', $entry) }}" class="wp-calendar-entry">
                                        <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }} wp-pill--xs">{{ __($entry->status->labelKey()) }}</span>
                                        <span class="wp-calendar-entry-title">{{ \Illuminate\Support\Str::limit($entry->description, 40) }}</span>
                                    </a>
                                @else
                                    <a href="{{ route('tasks.show', $entry) }}" class="wp-calendar-entry">
                                        <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }} wp-pill--xs">{{ __($entry->status->labelKey()) }}</span>
                                        <span class="wp-calendar-entry-title">{{ \Illuminate\Support\Str::limit($entry->issue?->description ?? $entry->note, 40) }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="wp-stack-tight">
            @foreach ($days as $day)
                @php
                    $dateKey = $day->toDateString();
                    $entries = $entriesByDate->get($dateKey, collect());
                @endphp
                <div class="wp-card wp-card-pad wp-stack-tight" wire:key="cal-day-{{ $dateKey }}">
                    <h3 class="wp-section-title">{{ $day->isoFormat('dddd D MMMM') }}</h3>
                    @forelse ($entries as $entry)
                        @if ($entryType === 'issues')
                            <a href="{{ route('issues.show', $entry) }}" class="wp-calendar-entry wp-calendar-entry--row">
                                <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }}">{{ __($entry->status->labelKey()) }}</span>
                                <span>{{ $entry->description }}</span>
                            </a>
                        @else
                            <a href="{{ route('tasks.show', $entry) }}" class="wp-calendar-entry wp-calendar-entry--row">
                                <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }}">{{ __($entry->status->labelKey()) }}</span>
                                <span>{{ $entry->issue?->description ?? $entry->note }}</span>
                            </a>
                        @endif
                    @empty
                        <p class="wp-muted">{{ __('calendar.empty_day') }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif
</div>
