@props([
    'teamBuckets',
    'attentionItems',
    'statusFilter',
    'boardLimit' => 0,
    'teamPageSize' => 50,
    'showForceClose' => false,
    'showTeam' => false,
    'kpis' => null,
])

@php
    use App\Enums\TimePresenceStatusFilter;

    $pageSize = $boardLimit > 0 ? $boardLimit : $teamPageSize;

    $attentionByShiftId = $attentionItems->mapWithKeys(
        fn ($item) => [$item->shift->id => $item]
    );

    $presentShifts = collect();
    $absentWorkers = collect();
    $totalPresent = 0;
    $totalAbsent = $teamBuckets->sum('absentCount');

    if ($statusFilter === TimePresenceStatusFilter::Attention) {
        $presentShifts = $attentionItems
            ->map(fn ($item) => $item->shift)
            ->sortBy(fn ($shift) => $shift->clock_in_at)
            ->values();
        $totalPresent = $presentShifts->count();
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
    } elseif ($statusFilter !== TimePresenceStatusFilter::Absent) {
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
        || $statusFilter === TimePresenceStatusFilter::Absent
        || $statusFilter === TimePresenceStatusFilter::Attention;
@endphp

@if ($teamBuckets->isEmpty())
    <p class="wp-muted">{{ __('time.presence.empty_section') }}</p>
@else
    @if ($kpis !== null)
        <div class="wp-time-presence-board__summary">
            @if ($kpis->attention > 0)
                <a href="{{ route('time.alarms.index') }}" class="wp-pill wp-pill--progress wp-time-presence-board__summary-pill">
                    <x-wp-icon name="alert-triangle" class="wp-time-presence-board__summary-icon" />
                    {{ __('time.presence.board_attention_pill', ['count' => $kpis->attention]) }}
                </a>
            @endif
            @if ($kpis->notClockedIn > 0)
                <button type="button"
                        class="wp-pill wp-time-presence-board__summary-pill wp-time-presence-board__summary-pill--muted"
                        wire:click="setStatusFilter('absent')">
                    <x-wp-icon name="team" class="wp-time-presence-board__summary-icon" />
                    {{ __('time.presence.board_expected_pill', ['count' => $kpis->notClockedIn]) }}
                </button>
            @endif
        </div>
    @endif

    <div class="wp-time-presence-board">
        @if ($showPresentColumn)
            <section class="wp-time-presence-board__column" aria-labelledby="presence-board-present-heading">
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
            <section class="wp-time-presence-board__column" aria-labelledby="presence-board-absent-heading">
                <header class="wp-time-presence-board__head">
                    <div>
                        <h2 id="presence-board-absent-heading" class="wp-time-presence-board__title">
                            {{ $statusFilter === TimePresenceStatusFilter::Attention
                                ? __('time.presence.attention_title')
                                : __('time.presence.board_absent_title') }}
                        </h2>
                        <p class="wp-time-presence-board__subtitle wp-muted">
                            {{ $statusFilter === TimePresenceStatusFilter::Attention
                                ? __('time.presence.attention_board_subtitle')
                                : __('time.presence.not_clocked_in_subtitle') }}
                        </p>
                    </div>
                    <span class="wp-pill wp-pill--progress wp-tabular">
                        {{ $statusFilter === TimePresenceStatusFilter::Attention ? $totalPresent : $totalAbsent }}
                    </span>
                </header>

                <div class="wp-time-presence-board__list">
                    @if ($statusFilter === TimePresenceStatusFilter::Attention)
                        @forelse ($visiblePresent as $shift)
                            @include('partials.wp-time-presence-card', [
                                'shift' => $shift,
                                'attentionItem' => $attentionByShiftId->get($shift->id),
                                'showForceClose' => $showForceClose,
                                'showTeam' => $showTeam,
                                'variant' => $shift->isOnBreak() ? 'break' : 'active',
                            ])
                        @empty
                            <p class="wp-muted wp-text-sm">{{ __('time.presence.no_attention') }}</p>
                        @endforelse
                    @else
                        @forelse ($visibleAbsent as $worker)
                            @include('partials.wp-time-presence-absent-card', [
                                'worker' => $worker,
                                'showTeam' => $showTeam,
                            ])
                        @empty
                            <p class="wp-muted wp-text-sm">{{ __('time.presence.empty_not_clocked_in') }}</p>
                        @endforelse
                    @endif
                </div>

                @if ($statusFilter === TimePresenceStatusFilter::Attention && $presentShifts->count() > $visiblePresent->count())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreBoard">
                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $presentShifts->count() - $visiblePresent->count())]) }}
                    </button>
                @elseif ($statusFilter !== TimePresenceStatusFilter::Attention && $absentWorkers->count() > $visibleAbsent->count())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreBoard">
                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $absentWorkers->count() - $visibleAbsent->count())]) }}
                    </button>
                @elseif ($totalAbsent > $visibleAbsent->count() && $statusFilter === TimePresenceStatusFilter::All)
                    <p class="wp-muted wp-text-sm">{{ __('time.presence.absent_truncated', ['count' => $totalAbsent - $visibleAbsent->count()]) }}</p>
                @endif
            </section>
        @endif
    </div>
@endif
