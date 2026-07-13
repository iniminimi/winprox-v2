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
                $shift = $item->shift;
                $hours = match ($item->type) {
                    \App\Enums\TimePresenceAttentionType::StaleShift => (int) config('time.stale_shift_hours', 16),
                    \App\Enums\TimePresenceAttentionType::LongShift => (int) config('time.long_shift_hours', 10),
                    \App\Enums\TimePresenceAttentionType::NoBreak => (int) config('time.break_reminder_hours', 6),
                };
                $labelKey = 'time.presence.attention.'.$item->type->value;
            @endphp
            <div class="wp-time-presence-attention__item" wire:key="presence-attention-{{ $item->type->value }}-{{ $shift->id }}">
                <p class="wp-time-presence-attention__reason">
                    <x-wp-icon name="alert-triangle" class="wp-time-presence-attention__reason-icon" />
                    <span>{{ __($labelKey, ['hours' => $hours]) }}</span>
                </p>
                @include('partials.wp-time-presence-row', [
                    'shift' => $shift,
                    'showForceClose' => $showForceClose,
                    'showTeam' => true,
                    'variant' => $shift->isOnBreak() ? 'break' : 'active',
                ])
            </div>
        @endforeach
    </div>

    @if ($compact && $total > $items->count())
        <button type="button" class="btn btn--ghost btn--sm" wire:click="setStatusFilter('attention')">
            {{ __('time.presence.attention_show_all', ['count' => $total]) }}
        </button>
    @endif
</section>
