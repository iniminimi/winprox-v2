@props([
    'shifts',
    'absentWorkers',
    'showForceClose' => false,
    'staleHours' => 16,
    'attentionItems' => null,
])

@php
    use App\Enums\TimePresenceAttentionType;

    $absenceMessageByShiftId = collect($attentionItems ?? [])
        ->filter(fn ($item) => $item->shift !== null && $item->type === TimePresenceAttentionType::AbsenceClockIn)
        ->mapWithKeys(fn ($item) => [$item->shift->id => $item->message()]);
@endphp

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
                        'attentionMessage' => $absenceMessageByShiftId->get($shift->id),
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
