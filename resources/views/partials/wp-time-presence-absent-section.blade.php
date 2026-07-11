@props(['workers'])

<section class="wp-time-presence-section wp-time-presence-section--absent">
    <header class="wp-time-presence-section__head">
        <div class="wp-time-presence-section__head-main">
            <span class="wp-time-presence-section__icon wp-time-presence-section__icon--absent">
                <x-wp-icon name="team" />
            </span>
            <div class="wp-time-presence-section__titles">
                <h2 class="wp-time-presence-section__title">
                    {{ __('time.presence.not_clocked_in') }}
                    <span class="wp-pill wp-pill--closed">{{ $workers->count() }}</span>
                </h2>
                <p class="wp-time-presence-section__subtitle">{{ __('time.presence.not_clocked_in_subtitle') }}</p>
            </div>
        </div>
        <p class="wp-time-presence-section__updated">
            <x-wp-icon name="clock" class="wp-time-presence-section__updated-icon" />
            <span>{{ now()->format('H:i') }}</span>
        </p>
    </header>

    @if ($workers->isEmpty())
        <p class="wp-muted wp-time-presence-section__empty">{{ __('time.presence.empty_not_clocked_in') }}</p>
    @else
        <div class="wp-time-presence-section__list">
            @foreach ($workers as $worker)
                @php
                    $initial = mb_strtoupper(mb_substr(trim((string) ($worker->first_name ?? '?')), 0, 1));
                @endphp
                <article class="wp-time-presence-card wp-time-presence-card--absent" wire:key="not-clocked-{{ $worker->id }}">
                    <div class="wp-time-presence-card__identity">
                        <span class="wp-time-presence-card__initial wp-time-presence-card__initial--absent" aria-hidden="true">{{ $initial }}</span>
                        <div class="wp-time-presence-card__identity-copy">
                            <strong class="wp-time-presence-card__name">{{ $worker->displayName() }}</strong>
                            <p class="wp-muted wp-text-sm">{{ $worker->team?->name }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
