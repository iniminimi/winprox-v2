@props([
    'shift',
    'attentionItem' => null,
    'showForceClose' => false,
    'showTeam' => false,
    'variant' => 'active',
])

@php
    use App\Support\Time\WorkDurationFormatter;

    $isOnBreak = $variant === 'break' || $shift->isOnBreak();
    $attentionHours = null;
    $attentionLabel = null;

    if ($attentionItem !== null) {
        $attentionHours = $attentionItem->type->thresholdValue();
        $attentionLabel = __('time.presence.attention.'.$attentionItem->type->value, ['hours' => $attentionHours]);
    }

    $avatarTone = match (true) {
        $isOnBreak => 'break',
        $attentionItem !== null => 'attention',
        default => 'present',
    };
@endphp

<article @class([
    'wp-time-presence-card',
    'wp-time-presence-card--break' => $isOnBreak,
    'wp-time-presence-card--attention' => $attentionItem !== null,
]) wire:key="presence-card-{{ $shift->id }}">
    <div class="wp-time-presence-card__avatar-wrap">
        <x-wp-worker-avatar
            :worker="$shift->worker"
            size="md"
            :tone="$avatarTone"
            @class([
                'wp-time-presence-card__avatar',
                'wp-time-presence-card__avatar--break' => $isOnBreak,
                'wp-time-presence-card__avatar--attention' => $attentionItem !== null && ! $isOnBreak,
            ])
        />
        <span @class([
            'wp-time-presence-card__status-dot',
            'wp-time-presence-card__status-dot--break' => $isOnBreak,
            'wp-time-presence-card__status-dot--attention' => $attentionItem !== null,
        ]) aria-hidden="true"></span>
    </div>

    <div class="wp-time-presence-card__content">
        <div class="wp-time-presence-card__head">
            <h3 class="wp-time-presence-card__name">{{ $shift->worker?->displayName() }}</h3>
            @if ($showTeam)
                <p class="wp-time-presence-card__team wp-muted">{{ $shift->team?->localizedName() }}</p>
            @endif
        </div>

        <div class="wp-time-presence-card__metrics">
            <span class="wp-time-presence-card__metric">
                <x-wp-icon name="clock" class="wp-time-presence-card__metric-icon" />
                <span class="wp-time-presence-card__metric-label">{{ __('time.presence.present') }}</span>
                <strong class="wp-time-presence-card__metric-value wp-tabular">{{ $shift->clock_in_at->format('H:i') }}</strong>
            </span>
            <span class="wp-time-presence-card__metric">
                <x-wp-icon name="hourglass" class="wp-time-presence-card__metric-icon" />
                <span class="wp-time-presence-card__metric-label">{{ __('time.presence.hours_label') }}</span>
                <strong class="wp-time-presence-card__metric-value wp-tabular">
                    {{ WorkDurationFormatter::format($shift->netWorkMinutes()) }}
                </strong>
            </span>
        </div>

        @if ($isOnBreak && $attentionLabel === null)
            <p class="wp-time-presence-card__inline-note wp-time-presence-card__inline-note--break">
                {{ __('time.presence.on_break') }}
            </p>
        @endif
    </div>

    <div class="wp-time-presence-card__aside">
        @if ($showForceClose)
            <button type="button" class="btn btn--surface btn--sm wp-time-presence-card__action" wire:click="openForceClose({{ $shift->id }})">
                <x-wp-icon name="logout" class="wp-time-presence-card__action-icon" />
                <span>{{ __('time.presence.force_close') }}</span>
            </button>
        @endif

        @if ($attentionLabel !== null)
            <p class="wp-time-presence-card__aside-note">
                <x-wp-icon name="alert-triangle" class="wp-time-presence-card__aside-note-icon" />
                <span>{{ $attentionLabel }}</span>
            </p>
        @endif
    </div>
</article>
