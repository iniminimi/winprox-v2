<div>
    {{-- Print-knop (verdwijnt bij afdrukken) --}}
    <div class="no-print" style="position: fixed; top: 1.5rem; right: 1.5rem; z-index: 100; display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn--primary" onclick="window.print()">
            Afdrukken / Opslaan als PDF
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn--ghost">Terug</a>
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
                WinProx Handleiding
            </h1>
            <p style="font-size: 1.25rem; color: var(--wp-text-secondary); margin: 0 0 0.5rem;">
                Facilitaire meldingsapp — beheerders &amp; medewerkers
            </p>
            <p style="font-size: 0.9rem; color: var(--wp-text-muted); margin: 0;">
                Gegenereerd op {{ $generatedAt }}
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
            <p style="font-weight: 600; color: var(--wp-text); margin: 0 0 1rem;">Inhoud</p>
            <ol style="margin: 0; padding: 0 0 0 1.25rem; line-height: 2; color: var(--wp-text-body);">
                @foreach ($chapters as $index => $chapter)
                    <li>{{ $chapter['title'] }}</li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- Hoofdstukken --}}
    @foreach ($chapters as $index => $chapter)
        @php $isLast = $loop->last; @endphp
        <div
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
                ">Hoofdstuk {{ $index + 1 }}</span>
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
                    ">Statussen</p>

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
        WinProx &mdash; Facilitaire meldingsapp &mdash; {{ $generatedAt }}
    </div>
</div>
