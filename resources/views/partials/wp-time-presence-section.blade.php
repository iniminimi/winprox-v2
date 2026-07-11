@props([
    'section' => 'present',
    'title',
    'subtitle',
    'shifts',
    'showForceClose' => false,
    'staleHours' => 16,
])

@php
    use App\Support\Time\WorkDurationFormatter;

    $sectionIcon = match ($section) {
        'on_break' => 'hourglass',
        default => 'team',
    };
@endphp

<section @class([
    'wp-time-presence-section',
    'wp-time-presence-section--present' => $section === 'present',
    'wp-time-presence-section--break' => $section === 'on_break',
])>
    <header class="wp-time-presence-section__head">
        <div class="wp-time-presence-section__head-main">
            <span @class([
                'wp-time-presence-section__icon',
                'wp-time-presence-section__icon--present' => $section === 'present',
                'wp-time-presence-section__icon--break' => $section === 'on_break',
            ])>
                <x-wp-icon :name="$sectionIcon" />
            </span>
            <div class="wp-time-presence-section__titles">
                <h2 class="wp-time-presence-section__title">
                    {{ $title }}
                    <span @class([
                        'wp-pill',
                        'wp-pill--done' => $section === 'present',
                        'wp-pill--progress' => $section === 'on_break',
                    ])>{{ $shifts->count() }}</span>
                </h2>
                <p class="wp-time-presence-section__subtitle">{{ $subtitle }}</p>
            </div>
        </div>
        <p class="wp-time-presence-section__updated">
            <x-wp-icon name="clock" class="wp-time-presence-section__updated-icon" />
            <span>{{ now()->format('H:i') }}</span>
        </p>
    </header>

    @if ($shifts->isEmpty())
        <p class="wp-muted wp-time-presence-section__empty">{{ __('time.presence.empty_section') }}</p>
    @else
        <div class="wp-time-presence-section__list">
            @foreach ($shifts as $shift)
                @php
                    $isStale = $shift->clock_in_at->lt(now()->subHours(max(1, (int) $staleHours)));
                    $initial = mb_strtoupper(mb_substr(trim((string) ($shift->worker?->first_name ?? '?')), 0, 1));
                @endphp
                <article class="wp-time-presence-card" wire:key="presence-shift-{{ $shift->id }}">
                    <div class="wp-time-presence-card__identity">
                        <span class="wp-time-presence-card__initial" aria-hidden="true">{{ $initial }}</span>
                        <div class="wp-time-presence-card__identity-copy">
                            <strong class="wp-time-presence-card__name">{{ $shift->worker?->displayName() }}</strong>
                            <p class="wp-muted wp-text-sm">{{ $shift->team?->name }}</p>
                        </div>
                    </div>

                    <div class="wp-time-presence-card__meta">
                        <div class="wp-time-presence-card__clock">
                            <x-wp-icon name="clock" class="wp-time-presence-card__clock-icon" />
                            <div>
                                <p class="wp-time-presence-card__clock-time">{{ $shift->clock_in_at->format('H:i') }}</p>
                                <p class="wp-muted wp-text-sm">{{ $shift->clockInClockPoint?->name }}</p>
                                <p class="wp-muted wp-text-sm">{{ __('time.presence.worked_so_far', ['duration' => WorkDurationFormatter::format($shift->netWorkMinutes())]) }}</p>
                            </div>
                        </div>
                        @if ($isStale)
                            <p class="wp-error wp-text-sm">{{ __('time.presence.stale_warning', ['hours' => $staleHours]) }}</p>
                        @endif
                    </div>

                    @if ($showForceClose)
                        <div class="wp-time-presence-card__action">
                            <button type="button" class="btn btn--surface btn--sm" wire:click="openForceClose({{ $shift->id }})">
                                <x-wp-icon name="logout" class="wp-time-presence-card__action-icon" />
                                {{ __('time.presence.force_close') }}
                            </button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
