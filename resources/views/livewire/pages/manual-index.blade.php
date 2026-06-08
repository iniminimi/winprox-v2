<div>
    {{-- Print-knop (verdwijnt bij afdrukken) --}}
    <div class="no-print" style="position: fixed; top: 1.5rem; right: 1.5rem; z-index: 100; display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn--primary" onclick="window.print()">
            {{ __('manual.cover.print') }}
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn--ghost">{{ __('manual.cover.back') }}</a>
    </div>

    {{-- Taalkeuze (verdwijnt bij afdrukken) --}}
    <div class="no-print" style="position: fixed; top: 5.5rem; right: 1.5rem; z-index: 100; display: flex; gap: 0.4rem;">
        @foreach ($this->availableLocales as $locale)
            <button
                type="button"
                class="btn btn--sm {{ $locale === $lang ? 'btn--primary' : 'btn--ghost' }}"
                wire:click="changeLocale('{{ $locale }}')"
            >
                {{ strtoupper($locale) }}
            </button>
        @endforeach
    </div>

    {{-- Cover-pagina --}}
    <div class="wp-manual-chapter" style="
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        text-align: center;
        padding: 4rem 2rem;
        gap: 2rem;
    ">
        <img
            src="{{ asset('images/Winprox_logo_300.png') }}"
            alt="WinProx"
            style="max-width: 220px; height: auto;"
        >

        <div style="margin-top: 2rem;">
            <h1 style="font-size: 2.75rem; font-weight: 700; letter-spacing: -0.04em; color: var(--wp-text); margin: 0 0 0.75rem;">
                {{ __('manual.cover.title') }}
            </h1>
            <p style="font-size: 1.25rem; color: var(--wp-text-secondary); margin: 0 0 0.5rem;">
                {{ __('manual.cover.subtitle') }}
            </p>
            <p style="font-size: 0.9rem; color: var(--wp-text-muted); margin: 0;">
                {{ __('manual.cover.generated', ['date' => $generatedAt]) }}
            </p>
        </div>

        <div style="
            margin-top: 3rem;
            padding: 1.5rem 2.5rem;
            background: var(--wp-surface);
            border: 1px solid var(--wp-border);
            border-radius: 12px;
            max-width: 520px;
            text-align: left;
        ">
            <p style="font-weight: 600; color: var(--wp-text); margin: 0 0 1rem;">{{ __('manual.cover.contents') }}</p>
            <ol style="margin: 0; padding: 0 0 0 1.25rem; line-height: 2; color: var(--wp-text-body);">
                @foreach ($chapters as $index => $chapter)
                    @php $slug = str_replace('.', '-', $chapter['key']); @endphp
                    <li><a href="#chapter-{{ $slug }}" style="color: var(--wp-accent-text); text-decoration: none;">{{ $chapter['title'] }}</a></li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- Pagina 2: Stappenplan --}}
    <div class="wp-manual-chapter" style="
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 100vh;
        padding: 4rem 3rem;
        max-width: 900px;
        margin: 0 auto;
        width: 100%;
    ">
        <p style="
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--wp-accent-text);
            margin: 0 0 1rem;
        ">{{ __('manual.getting_started.label') }}</p>
        <h2 style="
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--wp-text);
            margin: 0 0 0.5rem;
        ">{{ __('manual.getting_started.title') }}</h2>
        <p style="
            color: var(--wp-text-body);
            font-size: 1rem;
            margin: 0 0 3rem;
        ">{{ __('manual.getting_started.intro') }}</p>

        <ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1.25rem;">
            @foreach (range(1, 5) as $i)
                @php $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT); @endphp
                <li style="
                    display: grid;
                    grid-template-columns: 3rem 1fr;
                    gap: 1.25rem;
                    align-items: start;
                    padding: 1.25rem 1.5rem;
                    background: var(--wp-surface);
                    border: 1px solid var(--wp-border);
                    border-radius: 12px;
                ">
                    <span style="
                        font-size: 1.5rem;
                        font-weight: 800;
                        color: var(--wp-accent-text);
                        line-height: 1;
                        padding-top: 0.1rem;
                    ">{{ $num }}</span>
                    <div>
                        <p style="font-weight: 700; color: var(--wp-text); margin: 0 0 0.35rem; font-size: 1rem;">{{ __('manual.step_' . $i . '_title') }}</p>
                        <p style="color: var(--wp-text-body); font-size: 0.9rem; line-height: 1.6; margin: 0;">{{ __('manual.step_' . $i . '_text') }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Hoofdstukken --}}
    @foreach ($chapters as $index => $chapter)
        @php $isLast = $loop->last; $slug = str_replace('.', '-', $chapter['key']); @endphp
        <div
            id="chapter-{{ $slug }}"
            class="{{ $isLast ? '' : 'wp-manual-chapter' }}"
            style="padding: 3rem 2.5rem; max-width: 900px; margin: 0 auto; width: 100%;"
        >
            {{-- Hoofdstuknummer + titel --}}
            <div style="
                border-bottom: 2px solid var(--wp-accent);
                padding-bottom: 0.75rem;
                margin-bottom: 2rem;
                display: flex;
                align-items: baseline;
                gap: 1rem;
            ">
                <span style="
                    font-size: 0.8rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: var(--wp-accent-text);
                    white-space: nowrap;
                ">{{ __('manual.chapter') }} {{ $index + 1 }}</span>
                <h2 style="
                    font-size: 1.6rem;
                    font-weight: 700;
                    letter-spacing: -0.03em;
                    color: var(--wp-text);
                    margin: 0;
                ">{{ $chapter['title'] }}</h2>
            </div>

            {{-- Acties --}}
            @if (!empty($chapter['actions']))
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    @foreach ($chapter['actions'] as $action)
                        <div style="
                            display: grid;
                            grid-template-columns: 220px 1fr;
                            gap: 1rem;
                            padding: 1rem 1.25rem;
                            background: var(--wp-surface);
                            border: 1px solid var(--wp-border);
                            border-radius: 10px;
                        ">
                            <div style="
                                font-weight: 700;
                                color: var(--wp-text);
                                font-size: 0.95rem;
                                padding-top: 0.1rem;
                            ">{{ $action['label'] }}</div>
                            <div style="
                                color: var(--wp-text-body);
                                font-size: 0.9rem;
                                line-height: 1.6;
                                word-break: break-word;
                                overflow-wrap: anywhere;
                            ">{{ $action['text'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Statussen --}}
            @if (!empty($chapter['statuses']))
                <div style="margin-top: 2rem;">
                    <p style="
                        font-weight: 600;
                        color: var(--wp-text);
                        margin: 0 0 0.75rem;
                        font-size: 0.9rem;
                        text-transform: uppercase;
                        letter-spacing: 0.06em;
                    ">{{ __('manual.statuses') }}</p>

                    @if ($chapter['status_note'])
                        <p style="
                            color: var(--wp-text-muted);
                            font-size: 0.875rem;
                            margin: 0 0 1rem;
                            font-style: italic;
                        ">{{ $chapter['status_note'] }}</p>
                    @endif

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach ($chapter['statuses'] as $status)
                            <div style="
                                display: grid;
                                grid-template-columns: 160px 1fr;
                                gap: 0.75rem;
                                align-items: start;
                                padding: 0.6rem 1rem;
                                background: var(--wp-bg);
                                border-radius: 8px;
                                border: 1px solid var(--wp-border);
                            ">
                                <span class="wp-pill wp-pill--{{ $status['pill'] }}" style="justify-self: start;">
                                    {{ $status['label'] }}
                                </span>
                                <span style="color: var(--wp-text-body); font-size: 0.875rem; line-height: 1.5;">
                                    {{ $status['text'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endforeach

    {{-- Footer --}}
    <div style="
        text-align: center;
        padding: 2rem;
        border-top: 1px solid var(--wp-border);
        color: var(--wp-text-muted);
        font-size: 0.8rem;
        max-width: 900px;
        margin: 0 auto;
    ">
        {{ __('manual.footer', ['date' => $generatedAt]) }}
    </div>
</div>
