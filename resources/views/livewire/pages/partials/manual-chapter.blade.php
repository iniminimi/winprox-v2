<div
    id="chapter-{{ $slug }}"
    class="{{ $isLast ? '' : 'wp-manual-chapter' }}"
    style="padding: 3rem 2.5rem; max-width: 900px; margin: 0 auto; width: 100%;"
>
    {{-- Hoofdstuknummer + titel --}}
    <div class="wp-manual-chapter__heading">
        <span style="
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--wp-accent-text);
            white-space: nowrap;
        ">{{ __('manual.chapter') }} {{ $index + 1 }}</span>
        @if (!empty($chapter['icon']))
            <span class="wp-manual-chapter__icon" aria-hidden="true">
                <x-wp-icon :name="$chapter['icon']" />
            </span>
        @endif
        <h2 style="
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--wp-text);
            margin: 0;
        ">{{ $chapter['title'] }}</h2>
        <a href="#manual-toc" class="btn btn--ghost btn--sm no-print wp-manual-chapter__toc-link" title="Terug naar inhoudsopgave">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
        </a>
    </div>

    @if (! empty($manualScreenshotUrl))
        <figure @class([
            'wp-manual-screenshot',
            'wp-manual-screenshot--portal' => ! empty($manualScreenshotPortal),
        ])>
            <img
                class="wp-manual-screenshot__img"
                src="{{ $manualScreenshotUrl }}"
                alt="{{ __('manual.screenshot_alt', ['title' => $chapter['title']]) }}"
                loading="lazy"
            >
        </figure>
    @endif

    @if (!empty($chapter['intro']))
        <p style="
            color: var(--wp-text-body);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 0 1.5rem;
        ">{{ $chapter['intro'] }}</p>
    @endif

    {{-- Acties --}}
    @if (!empty($chapter['actions']))
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            @foreach ($chapter['actions'] as $action)
                <div @style([
                    'display: flex',
                    'flex-direction: column',
                    'gap: 0.5rem',
                    'padding: 1rem 1.25rem',
                    'background: var(--wp-surface)',
                    'border: 1px solid var(--wp-border)',
                    'border-radius: 10px',
                    'margin-inline-start: 1.75rem' => ! empty($action['nested']),
                ])>
                    <div style="
                        font-weight: 700;
                        color: var(--wp-text);
                        font-size: 0.95rem;
                    ">{{ $action['label'] }}</div>
                    <div style="
                        color: var(--wp-text-body);
                        font-size: 0.9rem;
                        line-height: 1.6;
                        word-break: break-word;
                        overflow-wrap: anywhere;
                    ">{!! $action['text'] !!}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Statussen --}}
    @if (!empty($chapter['statuses']))
        <div style="margin-top: {{ ! empty($chapter['actions']) ? '2rem' : '0' }};">
            @if (!empty($chapter['actions']))
            <p style="
                font-weight: 600;
                color: var(--wp-text);
                margin: 0 0 0.75rem;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            ">{{ __('manual.statuses') }}</p>
            @endif

            @if ($chapter['status_note'])
                <p style="
                    color: var(--wp-text-muted);
                    font-size: 0.875rem;
                    margin: 0 0 1rem;
                    font-style: italic;
                ">{{ $chapter['status_note'] }}</p>
            @endif

            <div class="wp-manual-status-list">
                @foreach ($chapter['statuses'] as $status)
                    <div class="wp-manual-status-list__row">
                        <span class="wp-pill wp-pill--{{ $status['pill'] }}">{{ $status['label'] }}</span>
                        <span class="wp-manual-status-list__text">{{ $status['text'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
