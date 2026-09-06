@props([
    'teamBuckets',
    'attentionItems',
    'statusFilter',
    'boardLimit' => 0,
    'teamPageSize' => 50,
    'showForceClose' => false,
    'showTeam' => false,
])

@php
    use App\Enums\TimePresenceStatusFilter;

    $pageSize = $boardLimit > 0 ? $boardLimit : $teamPageSize;

    $attentionByShiftId = $attentionItems
        ->filter(fn ($item) => $item->shift !== null)
        ->mapWithKeys(fn ($item) => [$item->shift->id => $item]);

    $rosterViewItems = $attentionItems
        ->filter(fn ($item) => $item->rosterView !== null)
        ->values();

    $presentShifts = collect();
    $absentWorkers = collect();
    $totalPresent = 0;
    $totalAbsent = $teamBuckets->sum('absentCount');

    if ($statusFilter === TimePresenceStatusFilter::Attention) {
        $presentShifts = $attentionItems
            ->map(fn ($item) => $item->shift)
            ->filter()
            ->sortBy(fn ($shift) => $shift->clock_in_at)
            ->values();
        $totalPresent = $attentionItems->count();
    } elseif ($statusFilter === TimePresenceStatusFilter::Active) {
        $presentShifts = $teamBuckets
            ->flatMap(fn ($bucket) => $bucket->activeShifts)
            ->sortBy(fn ($shift) => $shift->clock_in_at)
            ->values();
        $totalPresent = $teamBuckets->sum('activeCount');
    } elseif ($statusFilter === TimePresenceStatusFilter::Break) {
        $presentShifts = $teamBuckets
            ->flatMap(fn ($bucket) => $bucket->breakShifts)
            ->sortBy(fn ($shift) => $shift->clock_in_at)
            ->values();
        $totalPresent = $teamBuckets->sum('breakCount');
    } elseif ($statusFilter === TimePresenceStatusFilter::Absent) {
        $totalPresent = 0;
    } else {
        $presentShifts = $teamBuckets
            ->flatMap(fn ($bucket) => $bucket->activeShifts->merge($bucket->breakShifts))
            ->sortBy(fn ($shift) => $shift->clock_in_at)
            ->values();
        $totalPresent = $teamBuckets->sum('activeCount') + $teamBuckets->sum('breakCount');
    }

    if ($statusFilter === TimePresenceStatusFilter::All || $statusFilter === TimePresenceStatusFilter::Absent) {
        $absentWorkers = $teamBuckets
            ->flatMap(fn ($bucket) => $bucket->absentWorkers)
            ->values();
    }

    $visiblePresent = $presentShifts->take($pageSize);
    $visibleAbsent = $absentWorkers->take($pageSize);

    $showPresentColumn = $statusFilter !== TimePresenceStatusFilter::Absent
        && $statusFilter !== TimePresenceStatusFilter::Attention;
    $showAbsentColumn = $statusFilter === TimePresenceStatusFilter::All
        || $statusFilter === TimePresenceStatusFilter::Absent;
@endphp

@if ($teamBuckets->isEmpty() && $attentionItems->isEmpty())
    <p class="wp-muted">{{ __('time.presence.empty_section') }}</p>
@else
    <div class="wp-time-presence-board">
        @if ($showPresentColumn)
            <section class="wp-card wp-card-pad wp-time-presence-board__column" aria-labelledby="presence-board-present-heading">
                <header class="wp-time-presence-board__head">
                    <div>
                        <h2 id="presence-board-present-heading" class="wp-time-presence-board__title">{{ __('time.presence.present') }}</h2>
                        <p class="wp-time-presence-board__subtitle wp-muted">{{ __('time.presence.present_subtitle') }}</p>
                    </div>
                    <span class="wp-pill wp-pill--done wp-tabular">{{ $totalPresent }}</span>
                </header>

                <div class="wp-time-presence-board__list">
                    @forelse ($visiblePresent as $shift)
                        @include('partials.wp-time-presence-card', [
                            'shift' => $shift,
                            'attentionItem' => $attentionByShiftId->get($shift->id),
                            'showForceClose' => $showForceClose,
                            'showTeam' => $showTeam,
                            'variant' => $shift->isOnBreak() ? 'break' : 'active',
                        ])
                    @empty
                        <p class="wp-muted wp-text-sm">{{ __('time.presence.empty_section') }}</p>
                    @endforelse
                </div>

                @if ($presentShifts->count() > $visiblePresent->count())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreBoard">
                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $presentShifts->count() - $visiblePresent->count())]) }}
                    </button>
                @endif
            </section>
        @endif

        @if ($showAbsentColumn)
            <section class="wp-card wp-card-pad wp-time-presence-board__column" aria-labelledby="presence-board-absent-heading">
                <header class="wp-time-presence-board__head">
                    <div>
                        <h2 id="presence-board-absent-heading" class="wp-time-presence-board__title">
                            {{ __('time.presence.board_absent_title') }}
                        </h2>
                        <p class="wp-time-presence-board__subtitle wp-muted">
                            {{ __('time.presence.not_clocked_in_subtitle') }}
                        </p>
                    </div>
                    <span class="wp-pill wp-pill--progress wp-tabular">{{ $totalAbsent }}</span>
                </header>

                <div class="wp-time-presence-board__list">
                    @forelse ($visibleAbsent as $worker)
                        @include('partials.wp-time-presence-absent-card', [
                            'worker' => $worker,
                            'showTeam' => $showTeam,
                        ])
                    @empty
                        <p class="wp-muted wp-text-sm">{{ __('time.presence.empty_not_clocked_in') }}</p>
                    @endforelse
                </div>

                @if ($absentWorkers->count() > $visibleAbsent->count())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreBoard">
                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $absentWorkers->count() - $visibleAbsent->count())]) }}
                    </button>
                @elseif ($totalAbsent > $visibleAbsent->count())
                    <p class="wp-muted wp-text-sm">{{ __('time.presence.absent_truncated', ['count' => $totalAbsent - $visibleAbsent->count()]) }}</p>
                @endif
            </section>
        @endif

        @if ($statusFilter === TimePresenceStatusFilter::Attention)
            <section class="wp-card wp-card-pad wp-time-presence-board__column" aria-labelledby="presence-board-attention-heading">
                <header class="wp-time-presence-board__head">
                    <div>
                        <h2 id="presence-board-attention-heading" class="wp-time-presence-board__title">{{ __('time.presence.attention_title') }}</h2>
                        <p class="wp-time-presence-board__subtitle wp-muted">{{ __('time.presence.attention_board_subtitle') }}</p>
                    </div>
                    <span class="wp-pill wp-pill--progress wp-tabular">{{ $totalPresent }}</span>
                </header>

                <div class="wp-time-presence-board__list">
                    @forelse ($rosterViewItems as $item)
                        <article class="wp-time-presence-card wp-time-presence-card--attention" wire:key="presence-roster-{{ $item->listKey() }}">
                            <div class="wp-time-presence-card__content">
                                <div class="wp-time-presence-card__head">
                                    <h3 class="wp-time-presence-card__name">{{ $item->rosterView->displayName }}</h3>
                                    @if ($showTeam && filled($item->rosterView->teamName))
                                        <p class="wp-time-presence-card__team wp-muted">{{ $item->rosterView->teamName }}</p>
                                    @endif
                                </div>
                                <div class="wp-time-presence-card__metrics">
                                    <span class="wp-time-presence-card__metric">
                                        <x-wp-icon name="clock" class="wp-time-presence-card__metric-icon" />
                                        <span class="wp-time-presence-card__metric-label">{{ __('time.presence.attention.roster_viewed') }}</span>
                                        <strong class="wp-time-presence-card__metric-value wp-tabular">
                                            {{ $item->rosterView->viewedAt->timezone(config('app.timezone'))->format('d-m-Y H:i') }}
                                        </strong>
                                    </span>
                                </div>
                            </div>
                        </article>
                    @empty
                        @if ($visiblePresent->isEmpty())
                            <p class="wp-muted wp-text-sm">{{ __('time.presence.no_attention') }}</p>
                        @endif
                    @endforelse
                    @foreach ($visiblePresent as $shift)
                        @include('partials.wp-time-presence-card', [
                            'shift' => $shift,
                            'attentionItem' => $attentionByShiftId->get($shift->id),
                            'showForceClose' => $showForceClose,
                            'showTeam' => $showTeam,
                            'variant' => $shift->isOnBreak() ? 'break' : 'active',
                        ])
                    @endforeach
                </div>

                @if ($presentShifts->count() > $visiblePresent->count())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreBoard">
                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $presentShifts->count() - $visiblePresent->count())]) }}
                    </button>
                @endif
            </section>
        @endif
    </div>
@endif
