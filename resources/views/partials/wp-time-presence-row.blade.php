@props([
    'shift',
    'showForceClose' => false,
    'showTeam' => false,
    'variant' => 'active',
    'attentionMessage' => null,
])

@php
    use App\Support\Time\WorkDurationFormatter;

    $isOnBreak = $variant === 'break' || $shift->isOnBreak();
@endphp

<div class="wp-time-presence-row @if ($isOnBreak) wp-time-presence-row--break @endif" wire:key="presence-shift-{{ $shift->id }}">
    <x-wp-worker-hours-link :worker="$shift->worker" class="wp-time-presence-row__identity">
        <x-wp-worker-avatar
            :worker="$shift->worker"
            size="sm"
            :tone="$isOnBreak ? 'break' : 'present'"
            class="wp-time-presence-row__initial"
        />
        <div class="wp-time-presence-row__copy">
            <span class="wp-time-presence-row__name">
                @if ($attentionMessage)
                    <span class="wp-time-presence-card__bang" title="{{ $attentionMessage }}" aria-hidden="true">!</span>
                @endif
                {{ $shift->worker?->displayName() }}
            </span>
            @if ($attentionMessage)
                <span class="wp-muted wp-text-sm">{{ $attentionMessage }}</span>
            @endif
            @if ($shift->isManuallyClockedIn())
                <span class="wp-pill">{{ __('time.manual_clock_in.badge') }}</span>
            @endif
            @if ($showTeam)
                <span class="wp-muted wp-text-sm">{{ $shift->team?->localizedName() }}</span>
            @endif
            @include('partials.wp-time-presence-visit-pill', ['shift' => $shift])
        </div>
    </x-wp-worker-hours-link>

    <span class="wp-time-presence-row__clock wp-tabular">{{ $shift->clock_in_at->format('H:i') }}</span>

    <span class="wp-time-presence-row__duration wp-tabular">
        {{ WorkDurationFormatter::format($shift->netWorkMinutes()) }}
    </span>

    @if ($showForceClose)
        <div class="wp-time-presence-row__action">
            <button type="button" class="btn btn--surface btn--sm" wire:click="openForceClose({{ $shift->id }})">
                <x-wp-icon name="logout" class="wp-time-presence-row__action-icon" />
                <span class="wp-time-presence-row__action-label">{{ __('time.presence.force_close') }}</span>
            </button>
        </div>
    @endif
</div>
