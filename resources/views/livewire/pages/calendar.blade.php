<div class="wp-stack" data-manual-capture="calendar">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @elseif ($onboarding->showCategoriesOrLocationsBanner())
        <x-wp-onboarding-banner stage="categories" />
    @elseif ($onboarding->showClockPointBanner())
        <x-wp-onboarding-banner stage="clock_point" />
    @else
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="calendar"
                    :title="__('calendar.title')"
                    help-page="calendar"
                    :subtitle="__('calendar.subtitle')"
                />
            </div>
            <a href="{{ route('briefing.print', array_filter(['date' => $entryType === 'tasks' ? $currentDate : null])) }}"
               target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('calendar.briefing') }}</a>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
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
                    <button type="button"
                            class="btn btn--ghost btn--sm {{ $entryType === 'reservations' ? 'is-active' : '' }}"
                            wire:click="setEntryType('reservations')">
                        {{ __('calendar.type.reservations') }}
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
            <div class="wp-calendar-shell">
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
                                    @foreach ($entries->take(5) as $entry)
                                        @if ($entryType === 'issues')
                                            <a href="{{ route('issues.show', $entry) }}" class="wp-calendar-entry">
                                                <x-wp-ref-nr :id="$entry->id" class="wp-calendar-entry-nr" />
                                                <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }} wp-pill--xs">{{ __($entry->status->labelKey()) }}</span>
                                                <span class="wp-calendar-entry-title">{{ \Illuminate\Support\Str::limit($entry->localizedDescription(), 40) }}</span>
                                            </a>
                                        @elseif ($entryType === 'reservations')
                                            <a href="{{ route('reservations.index') }}" class="wp-calendar-entry">
                                                <span class="wp-pill wp-pill--{{ $entry->lifecycle()->pillVariant() }} wp-pill--xs">{{ __('reservations.lifecycle.'.$entry->lifecycle()->value) }}</span>
                                                <span class="wp-calendar-entry-title">{{ $entry->unit?->name }} · {{ $entry->start_at?->format('H:i') }}–{{ $entry->end_at?->format('H:i') }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('tasks.show', $entry) }}" class="wp-calendar-entry">
                                                <x-wp-ref-nr type="task" :id="$entry->id" class="wp-calendar-entry-nr" />
                                                <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }} wp-pill--xs">{{ __($entry->status->labelKey()) }}</span>
                                                <span class="wp-calendar-entry-title">{{ \Illuminate\Support\Str::limit($entry->displayDescription(), 40) }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                    @if ($entries->count() > 5)
                                        <div class="wp-calendar-more">
                                            +{{ $entries->count() - 5 }} {{ __('calendar.more') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="wp-stack-tight">
                @foreach ($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $entries = $entriesByDate->get($dateKey, collect());
                        $limit = $isDayView ? 50 : 20;
                        $offset = ($dayPage - 1) * $limit;
                        $paginatedEntries = $entries->slice($offset, $limit);
                        $totalPages = $entries->count() > 0 ? ceil($entries->count() / $limit) : 1;
                    @endphp
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="cal-day-{{ $dateKey }}">
                        <h3 class="wp-section-title">{{ $day->isoFormat('dddd D MMMM') }}</h3>
                        @forelse ($paginatedEntries as $entry)
                            @if ($entryType === 'issues')
                                <a href="{{ route('issues.show', $entry) }}" class="wp-calendar-entry wp-calendar-entry--row">
                                    <x-wp-ref-nr :id="$entry->id" />
                                    <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }}">{{ __($entry->status->labelKey()) }}</span>
                                    <span>{{ $entry->localizedDescription() }}</span>
                                </a>
                            @elseif ($entryType === 'reservations')
                                <a href="{{ route('reservations.index') }}" class="wp-calendar-entry wp-calendar-entry--row">
                                    <span class="wp-pill wp-pill--{{ $entry->lifecycle()->pillVariant() }}">{{ __('reservations.lifecycle.'.$entry->lifecycle()->value) }}</span>
                                    <span>{{ $entry->unit?->name }} · {{ $entry->guestFullName() }} · {{ $entry->start_at?->format('H:i') }}–{{ $entry->end_at?->format('H:i') }}</span>
                                </a>
                            @else
                                <a href="{{ route('tasks.show', $entry) }}" class="wp-calendar-entry wp-calendar-entry--row">
                                    <x-wp-ref-nr type="task" :id="$entry->id" />
                                    <span class="wp-pill wp-pill--{{ $entry->status->pillModifier() }}">{{ __($entry->status->labelKey()) }}</span>
                                    <span>{{ $entry->displayDescription() }}</span>
                                </a>
                            @endif
                        @empty
                            <p class="wp-muted">{{ __('calendar.empty_day') }}</p>
                        @endforelse
                        @if ($isDayView && $entries->count() > $limit)
                            <div class="wp-calendar-pagination">
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="previousPage" :disabled="{{ $dayPage === 1 }}">
                                    ‹
                                </button>
                                <span class="wp-text-sm">{{ $dayPage }} / {{ (int) $totalPages }}</span>
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="nextPage" :disabled="{{ $dayPage >= $totalPages }}">
                                    ›
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
