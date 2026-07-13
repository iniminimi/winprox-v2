@props([
    'shifts',
    'absentWorkers',
    'showForceClose' => false,
    'staleHours' => 16,
])

<section class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-section-title">{{ __('time.presence.search_results') }}</h2>

    @if ($shifts->isEmpty() && $absentWorkers->isEmpty())
        <p class="wp-muted">{{ __('time.presence.search_empty') }}</p>
    @else
        @if ($shifts->isNotEmpty())
            <div class="wp-time-presence-team-panel">
                @foreach ($shifts as $shift)
                    @include('partials.wp-time-presence-row', [
                        'shift' => $shift,
                        'showForceClose' => $showForceClose,
                        'showTeam' => true,
                        'variant' => $shift->isOnBreak() ? 'break' : 'active',
                    ])
                @endforeach
            </div>
        @endif

        @if ($absentWorkers->isNotEmpty())
            <p class="wp-time-presence-team-panel__heading">{{ __('time.presence.not_clocked_in') }}</p>
            <div class="wp-time-presence-team-panel">
                @foreach ($absentWorkers as $worker)
                    @include('partials.wp-time-presence-absent-row', ['worker' => $worker])
                @endforeach
            </div>
        @endif
    @endif
</section>
