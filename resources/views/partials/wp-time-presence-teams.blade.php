@props([
    'teamBuckets',
    'expandedTeams',
    'statusFilter',
    'teamShiftLimits' => [],
    'teamPageSize' => 50,
    'showForceClose' => false,
])

@php
    use App\Enums\TimePresenceStatusFilter;
@endphp

@if ($teamBuckets->isEmpty())
    <p class="wp-muted">{{ __('time.presence.empty_section') }}</p>
@else
    <div class="wp-time-presence-teams">
        @foreach ($teamBuckets as $bucket)
            @php
                $isExpanded = in_array($bucket->team->id, $expandedTeams, true);
                $activeLimit = $teamShiftLimits[$bucket->team->id] ?? $teamPageSize;
                $visibleActive = $bucket->activeShifts->take($activeLimit);
                $visibleBreak = $bucket->breakShifts->take($activeLimit);
            @endphp
            <div class="wp-disclosure-block" wire:key="presence-team-{{ $bucket->team->id }}">
                <button type="button"
                        class="wp-disclosure-block-toggle wp-team-row-toggle wp-time-presence-team-toggle"
                        wire:click="toggleTeam({{ $bucket->team->id }})"
                        aria-expanded="{{ $isExpanded ? 'true' : 'false' }}">
                    <x-wp-icon name="chevron-down" @class(['wp-disclosure-chevron', 'is-open' => $isExpanded]) />
                    <span class="wp-data-row-title">{{ $bucket->team->name }}</span>
                    <span class="wp-muted wp-time-presence-team-summary">
                        @if ($bucket->activeCount > 0)
                            {{ __('time.presence.team_summary_active', ['count' => $bucket->activeCount]) }}
                        @endif
                        @if ($bucket->breakCount > 0)
                            @if ($bucket->activeCount > 0) · @endif
                            {{ __('time.presence.team_summary_break', ['count' => $bucket->breakCount]) }}
                        @endif
                        @if ($bucket->absentCount > 0)
                            @if ($bucket->activeCount > 0 || $bucket->breakCount > 0) · @endif
                            {{ __('time.presence.team_summary_absent', ['count' => $bucket->absentCount]) }}
                        @endif
                        @if ($bucket->attentionCount > 0)
                            · <span class="wp-time-presence-team-summary__alert">{{ __('time.presence.team_summary_attention', ['count' => $bucket->attentionCount]) }}</span>
                        @endif
                    </span>
                </button>

                @if ($isExpanded)
                    <div class="wp-disclosure-panel wp-time-presence-team-panel">
                        @if ($statusFilter !== TimePresenceStatusFilter::Absent)
                            @if ($bucket->activeCount > 0 && $statusFilter !== TimePresenceStatusFilter::Break)
                                @if ($bucket->breakCount > 0 || $bucket->absentCount > 0)
                                    <p class="wp-time-presence-team-panel__heading">{{ __('time.presence.present') }}</p>
                                @endif
                                @foreach ($visibleActive as $shift)
                                    @include('partials.wp-time-presence-row', [
                                        'shift' => $shift,
                                        'showForceClose' => $showForceClose,
                                        'variant' => 'active',
                                    ])
                                @endforeach
                                @if ($bucket->activeCount > $visibleActive->count())
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreTeamShifts({{ $bucket->team->id }})">
                                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $bucket->activeCount - $visibleActive->count())]) }}
                                    </button>
                                @endif
                            @endif

                            @if ($bucket->breakCount > 0 && $statusFilter !== TimePresenceStatusFilter::Active)
                                <p class="wp-time-presence-team-panel__heading">{{ __('time.presence.on_break') }}</p>
                                @foreach ($visibleBreak as $shift)
                                    @include('partials.wp-time-presence-row', [
                                        'shift' => $shift,
                                        'showForceClose' => $showForceClose,
                                        'variant' => 'break',
                                    ])
                                @endforeach
                                @if ($bucket->breakCount > $visibleBreak->count())
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="loadMoreTeamShifts({{ $bucket->team->id }})">
                                        {{ __('time.presence.load_more', ['count' => min($teamPageSize, $bucket->breakCount - $visibleBreak->count())]) }}
                                    </button>
                                @endif
                            @endif
                        @endif

                        @if ($statusFilter === TimePresenceStatusFilter::Absent || $statusFilter === TimePresenceStatusFilter::All)
                            @if ($bucket->absentCount > 0 || $statusFilter === TimePresenceStatusFilter::Absent)
                                @if (
                                    $statusFilter === TimePresenceStatusFilter::Absent
                                    || $bucket->activeCount > 0
                                    || $bucket->breakCount > 0
                                )
                                    <p class="wp-time-presence-team-panel__heading">{{ __('time.presence.not_clocked_in') }}</p>
                                @endif
                                @forelse ($bucket->absentWorkers as $worker)
                                    @include('partials.wp-time-presence-absent-row', ['worker' => $worker])
                                @empty
                                    <p class="wp-muted wp-text-sm">{{ __('time.presence.empty_not_clocked_in') }}</p>
                                @endforelse
                                @if ($bucket->absentCount > $bucket->absentWorkers->count())
                                    <p class="wp-muted wp-text-sm">{{ __('time.presence.absent_truncated', ['count' => $bucket->absentCount - $bucket->absentWorkers->count()]) }}</p>
                                @endif
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
