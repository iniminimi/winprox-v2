@props([
    'worker',
    'showTeam' => false,
])

@php
    $initial = mb_strtoupper(mb_substr(trim((string) ($worker->first_name ?? '?')), 0, 1));
@endphp

<article class="wp-time-presence-card wp-time-presence-card--absent" wire:key="presence-absent-card-{{ $worker->id }}">
    <div class="wp-time-presence-card__main">
        <div class="wp-time-presence-card__avatar-wrap">
            <span class="wp-time-presence-card__avatar wp-time-presence-card__avatar--absent" aria-hidden="true">{{ $initial }}</span>
            <span class="wp-time-presence-card__status-dot wp-time-presence-card__status-dot--absent" aria-hidden="true"></span>
        </div>

        <div class="wp-time-presence-card__body">
            <div class="wp-time-presence-card__head">
                <h3 class="wp-time-presence-card__name">{{ $worker->displayName() }}</h3>
                @if ($showTeam)
                    <p class="wp-time-presence-card__team wp-muted">{{ $worker->team?->name }}</p>
                @endif
            </div>

            <p class="wp-time-presence-card__attention wp-time-presence-card__attention--absent">
                <x-wp-icon name="alert-triangle" class="wp-time-presence-card__attention-icon" />
                <span>{{ __('time.presence.not_clocked_in') }}</span>
            </p>
        </div>
    </div>
</article>
