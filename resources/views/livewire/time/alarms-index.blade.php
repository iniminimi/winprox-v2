<div class="wp-stack" wire:poll.visible.30s data-manual-capture="time-alarms">
    @php
        $activeAttentionType = \App\Enums\TimePresenceAttentionType::tryFrom((string) $attentionType);
    @endphp
    <x-wp-page-head-title
        :title="__('time.alarms.title')"
        help-page="time.alarms"
        :subtitle="__('time.alarms.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-card wp-filter-panel wp-time-alarms-toolbar">
        <div class="wp-filter-form wp-time-alarms-toolbar__form">
            <div class="wp-filter-form__row">
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="alarms-team">{{ __('time.filters.team') }}</label>
                    <select id="alarms-team" class="wp-select" wire:model.live="teamFilter">
                        <option value="">{{ __('time.filters.all_teams') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="alarms-location">{{ __('time.presence.location') }}</label>
                    <select id="alarms-location" class="wp-select" wire:model.live="locationFilter">
                        <option value="">{{ __('time.presence.all_locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="wp-time-presence-toolbar__meta">
                <div class="wp-time-presence-toolbar__actions">
                    @include('partials.wp-time-alarms-type-filters', [
                        'activeAttentionType' => $activeAttentionType,
                        'typeCounts' => $typeCounts,
                        'totalCount' => $totalCount,
                    ])
                </div>
                <p class="wp-time-presence-toolbar__updated wp-muted">
                    <x-wp-icon name="clock" class="wp-time-presence-toolbar__updated-icon" />
                    <span>{{ now()->format('H:i') }}</span>
                </p>
            </div>
        </div>
    </div>

    @if ($items->isEmpty())
        <p class="wp-muted">{{ __('time.alarms.empty') }}</p>
    @else
        @if ($filteredCount > $items->count())
            <p class="wp-muted wp-text-sm">{{ __('time.alarms.shown', ['shown' => $items->count(), 'total' => $filteredCount]) }}</p>
        @endif
        @php
            $grouped = $items->groupBy(fn ($item) => $item->type->value);
        @endphp
        <div class="wp-stack wp-time-alarms-groups">
            @foreach ($grouped as $type => $groupItems)
                @php
                    $typeEnum = \App\Enums\TimePresenceAttentionType::from($type);
                    $hours = $typeEnum->thresholdValue();
                    $typeTotal = $activeAttentionType !== null
                        ? $filteredCount
                        : (int) ($typeCounts[$type] ?? $groupItems->count());
                @endphp
                <section class="wp-card wp-card-pad wp-stack" wire:key="alarms-group-{{ $type }}">
                    <div class="wp-time-presence-attention__head">
                        <h2 class="wp-section-title">{{ __('time.presence.attention.'.$type, ['hours' => $hours]) }}</h2>
                        <span class="wp-pill wp-pill--progress">{{ $typeTotal }}</span>
                    </div>
                    <div class="wp-time-presence-attention__list">
                        @foreach ($groupItems as $item)
                            <div class="wp-time-presence-attention__item" wire:key="alarm-{{ $type }}-{{ $item->listKey() }}">
                                @if ($item->rosterView !== null)
                                    @include('partials.wp-time-roster-view-row', [
                                        'view' => $item->rosterView,
                                        'showTeam' => true,
                                    ])
                                @elseif ($item->shift !== null)
                                    @include('partials.wp-time-presence-row', [
                                        'shift' => $item->shift,
                                        'showForceClose' => true,
                                        'showTeam' => true,
                                        'variant' => $item->shift->isOnBreak() ? 'break' : 'active',
                                    ])
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if ($hasMore)
            <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMore">
                {{ __('time.presence.load_more', ['count' => min($pageSize, $filteredCount - $items->count())]) }}
            </button>
        @endif
    @endif

    @include('partials.wp-time-force-close-modal')
</div>
