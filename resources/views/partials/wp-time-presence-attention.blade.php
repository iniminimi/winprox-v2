@props([
    'items',
    'total',
    'showForceClose' => false,
    'staleHours' => 16,
    'compact' => true,
])

<section class="wp-card wp-card-pad wp-stack wp-time-presence-attention">
    <div class="wp-time-presence-attention__head">
        <h2 class="wp-section-title">{{ __('time.presence.attention_title') }}</h2>
        <span class="wp-pill wp-pill--progress">{{ $total }}</span>
    </div>

    <div class="wp-time-presence-attention__list">
        @foreach ($items as $item)
            @php
                $hours = $item->type->thresholdValue();
                $labelKey = 'time.presence.attention.'.$item->type->value;
            @endphp
            <div class="wp-time-presence-attention__item" wire:key="presence-attention-{{ $item->type->value }}-{{ $item->listKey() }}">
                <p class="wp-time-presence-attention__reason">
                    <x-wp-icon name="alert-triangle" class="wp-time-presence-attention__reason-icon" />
                    <span>{{ __($labelKey, ['hours' => $hours]) }}</span>
                </p>
                @if ($item->rosterView !== null)
                    @include('partials.wp-time-roster-view-row', [
                        'view' => $item->rosterView,
                        'showTeam' => true,
                    ])
                @elseif ($item->shift !== null)
                    @include('partials.wp-time-presence-row', [
                        'shift' => $item->shift,
                        'showForceClose' => $showForceClose,
                        'showTeam' => true,
                        'variant' => $item->shift->isOnBreak() ? 'break' : 'active',
                    ])
                @endif
            </div>
        @endforeach
    </div>

    @if ($compact && $total > $items->count())
        <a href="{{ route('time.alarms.index') }}" class="btn btn--ghost btn--sm">
            {{ __('time.presence.attention_show_all', ['count' => $total]) }}
        </a>
    @endif
</section>
