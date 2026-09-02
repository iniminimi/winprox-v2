@props([
    'worker',
    'showTeam' => false,
])

<article class="wp-time-presence-card wp-time-presence-card--absent" wire:key="presence-absent-card-{{ $worker->id }}">
    <div class="wp-time-presence-card__avatar-wrap">
        <x-wp-worker-avatar
            :worker="$worker"
            size="md"
            tone="absent"
            class="wp-time-presence-card__avatar wp-time-presence-card__avatar--absent"
        />
        <span class="wp-time-presence-card__status-dot wp-time-presence-card__status-dot--absent" aria-hidden="true"></span>
    </div>

    <div class="wp-time-presence-card__content">
        <div class="wp-time-presence-card__head">
            <h3 class="wp-time-presence-card__name">{{ $worker->displayName() }}</h3>
            @if ($showTeam)
                <p class="wp-time-presence-card__team wp-muted">{{ $worker->team?->localizedName() }}</p>
            @endif
        </div>

        <p class="wp-time-presence-card__inline-note wp-time-presence-card__inline-note--absent">
            {{ __('time.presence.not_clocked_in') }}
        </p>
    </div>
</article>
