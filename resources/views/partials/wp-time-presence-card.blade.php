@props([
    'shift',
    'attentionItem' => null,
    'showForceClose' => false,
    'showTeam' => false,
    'variant' => 'active',
])

@php
    use App\Enums\TimePresenceAttentionType;
    use App\Support\Time\WorkDurationFormatter;

    $initial = mb_strtoupper(mb_substr(trim((string) ($shift->worker?->first_name ?? '?')), 0, 1));
    $isOnBreak = $variant === 'break' || $shift->isOnBreak();
    $attentionHours = null;
    $attentionLabel = null;

    if ($attentionItem !== null) {
        $attentionHours = match ($attentionItem->type) {
            TimePresenceAttentionType::StaleShift => (int) config('time.stale_shift_hours', 16),
            TimePresenceAttentionType::LongShift => (int) config('time.long_shift_hours', 10),
            TimePresenceAttentionType::NoBreak => (int) config('time.break_reminder_hours', 6),
        };
        $attentionLabel = __('time.presence.attention.'.$attentionItem->type->value, ['hours' => $attentionHours]);
    }
@endphp

<article @class([
    'wp-time-presence-card',
    'wp-time-presence-card--break' => $isOnBreak,
    'wp-time-presence-card--attention' => $attentionItem !== null,
]) wire:key="presence-card-{{ $shift->id }}">
    <div class="wp-time-presence-card__main">
        <div class="wp-time-presence-card__avatar-wrap">
            <span @class([
                'wp-time-presence-card__avatar',
                'wp-time-presence-card__avatar--break' => $isOnBreak,
                'wp-time-presence-card__avatar--attention' => $attentionItem !== null && ! $isOnBreak,
            ]) aria-hidden="true">{{ $initial }}</span>
            <span @class([
                'wp-time-presence-card__status-dot',
                'wp-time-presence-card__status-dot--break' => $isOnBreak,
                'wp-time-presence-card__status-dot--attention' => $attentionItem !== null,
            ]) aria-hidden="true"></span>
        </div>

        <div class="wp-time-presence-card__body">
            <div class="wp-time-presence-card__head">
                <h3 class="wp-time-presence-card__name">{{ $shift->worker?->displayName() }}</h3>
                @if ($showTeam)
                    <p class="wp-time-presence-card__team wp-muted">{{ $shift->team?->name }}</p>
                @endif
            </div>

            <dl class="wp-time-presence-card__stats">
                <div class="wp-time-presence-card__stat">
                    <dt class="wp-time-presence-card__stat-label">
                        <x-wp-icon name="clock" class="wp-time-presence-card__stat-icon" />
                        <span>{{ __('time.presence.present') }}</span>
                    </dt>
                    <dd class="wp-time-presence-card__stat-value wp-tabular">{{ $shift->clock_in_at->format('H:i') }}</dd>
                </div>
                <div class="wp-time-presence-card__stat">
                    <dt class="wp-time-presence-card__stat-label">
                        <x-wp-icon name="hourglass" class="wp-time-presence-card__stat-icon" />
                        <span>{{ __('time.presence.hours_label') }}</span>
                    </dt>
                    <dd class="wp-time-presence-card__stat-value wp-tabular">
                        {{ WorkDurationFormatter::format($shift->netWorkMinutes()) }}
                    </dd>
                </div>
            </dl>

            @if ($attentionLabel !== null)
                <p class="wp-time-presence-card__attention">
                    <x-wp-icon name="alert-triangle" class="wp-time-presence-card__attention-icon" />
                    <span>{{ $attentionLabel }}</span>
                </p>
            @elseif ($isOnBreak)
                <p class="wp-time-presence-card__attention wp-time-presence-card__attention--break">
                    <x-wp-icon name="hourglass" class="wp-time-presence-card__attention-icon" />
                    <span>{{ __('time.presence.on_break') }}</span>
                </p>
            @endif
        </div>
    </div>

    @if ($showForceClose)
        <div class="wp-time-presence-card__action">
            <button type="button" class="btn btn--surface btn--sm" wire:click="openForceClose({{ $shift->id }})">
                <x-wp-icon name="logout" class="wp-time-presence-card__action-icon" />
                <span>{{ __('time.presence.force_close') }}</span>
            </button>
        </div>
    @endif
</article>
