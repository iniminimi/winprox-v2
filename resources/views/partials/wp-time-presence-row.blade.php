@props([
    'shift',
    'showForceClose' => false,
    'showTeam' => false,
    'variant' => 'active',
])

@php
    use App\Support\Time\WorkDurationFormatter;

    $initial = mb_strtoupper(mb_substr(trim((string) ($shift->worker?->first_name ?? '?')), 0, 1));
    $isOnBreak = $variant === 'break' || $shift->isOnBreak();
@endphp

<div class="wp-time-presence-row @if ($isOnBreak) wp-time-presence-row--break @endif" wire:key="presence-shift-{{ $shift->id }}">
    <div class="wp-time-presence-row__identity">
        <span class="wp-time-presence-row__initial" aria-hidden="true">{{ $initial }}</span>
        <div class="wp-time-presence-row__copy">
            <span class="wp-time-presence-row__name">{{ $shift->worker?->displayName() }}</span>
            @if ($showTeam)
                <span class="wp-muted wp-text-sm">{{ $shift->team?->localizedName() }}</span>
            @endif
        </div>
    </div>

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
