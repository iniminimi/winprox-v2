@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('comparison.meta_title') }}</title>
    @include('partials.social-meta', [
        'title' => __('comparison.social.og_title'),
        'description' => __('comparison.social.og_description'),
        'url' => route('comparison'),
    ])
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-welcome-shell">
    <div class="wp-welcome-top">
        <nav class="wp-welcome-nav" aria-label="{{ __('comparison.meta_title') }}">
            <div class="wp-welcome-nav-inner">
                @include('partials.wp-welcome-brand')
                <div class="wp-welcome-nav-links">
                    <a href="{{ route('welcome') }}">{{ __('welcome.back_home') }}</a>
                    <a href="{{ route('welcome') }}#hoe-het-werkt">{{ __('welcome.nav.how') }}</a>
                    <a href="{{ route('welcome') }}#melden">{{ __('welcome.nav.reporting') }}</a>
                    <a href="{{ route('welcome') }}#uitvoering">{{ __('welcome.nav.execution') }}</a>
                </div>
                <div class="wp-welcome-nav-actions">
                    @include('partials.wp-lang-switch', ['variant' => 'inline'])
                    <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.hero.cta_start') }}</a>
                </div>
            </div>
        </nav>
    </div>

    <main>
        <section class="wp-welcome-section wp-welcome-section--center" aria-labelledby="comparison-hero-title">
            <div class="wp-welcome-section-inner">
                <span class="wp-welcome-eyebrow">{{ __('comparison.hero.eyebrow') }}</span>
                <h1 id="comparison-hero-title" class="wp-welcome-h1">
                    {{ __('comparison.hero.title_before') }}
                    <em>{{ __('comparison.hero.title_highlight') }}</em>
                </h1>
                <p class="wp-welcome-lead">{{ __('comparison.hero.lead') }}</p>
                <div class="wp-welcome-cta-row">
                    <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('comparison.cta.trial') }}</a>
                    <a href="{{ route('contact.index') }}" class="btn btn--ghost btn--lg">{{ __('comparison.cta.more_info') }}</a>
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="comparison-table-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('comparison.table.title') }}</span>
                    <h2 id="comparison-table-title" class="wp-welcome-h2">{{ __('comparison.table.title') }}</h2>
                </div>

                <div class="wp-welcome-comparison-table">
                    <div class="wp-welcome-comparison-row wp-welcome-comparison-row--header">
                        <div class="wp-welcome-comparison-label"></div>
                        <div class="wp-welcome-comparison-cell">{{ __('comparison.table.other_label') }}</div>
                        <div class="wp-welcome-comparison-cell wp-welcome-comparison-cell--winprox">{{ __('comparison.table.winprox_label') }}</div>
                    </div>
                    @foreach (__('comparison.table.rows') as $row)
                        <div class="wp-welcome-comparison-row">
                            <div class="wp-welcome-comparison-label">{{ $row['label'] }}</div>
                            <div class="wp-welcome-comparison-cell">
                                <span class="wp-welcome-comparison-cell-kicker">{{ __('comparison.table.other_label') }}</span>
                                {{ $row['other'] }}
                            </div>
                            <div class="wp-welcome-comparison-cell wp-welcome-comparison-cell--winprox">
                                <span class="wp-welcome-comparison-cell-kicker">{{ __('comparison.table.winprox_label') }}</span>
                                {{ $row['winprox'] }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="wp-welcome-cta-row">
                    <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('comparison.cta.trial') }}</a>
                    <a href="{{ route('contact.index') }}" class="btn btn--ghost btn--lg">{{ __('comparison.cta.more_info') }}</a>
                </div>
            </div>
        </section>

        <section class="wp-welcome-section" aria-labelledby="comparison-flow-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('comparison.flow.eyebrow') }}</span>
                    <h2 id="comparison-flow-title" class="wp-welcome-h2">{{ __('comparison.flow.title') }}</h2>
                </div>
                <div class="wp-welcome-flow-grid wp-welcome-flow-grid--5">
                    @foreach (__('comparison.flow.steps') as $index => $step)
                        <div class="wp-welcome-flow-step">
                            <span class="wp-welcome-flow-step-label">{{ __('comparison.flow.eyebrow') }} {{ $index + 1 }}</span>
                            <span class="wp-welcome-flow-step-title">{{ $step }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="comparison-why-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('comparison.why.eyebrow') }}</span>
                    <h2 id="comparison-why-title" class="wp-welcome-h2">{{ __('comparison.why.title') }}</h2>
                </div>
                <div class="wp-welcome-sector-grid">
                    @foreach (__('comparison.why.cards') as $card)
                        <article class="wp-welcome-sector-card">
                            <h3>{{ $card['title'] }}</h3>
                            <p>{{ $card['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--center" aria-labelledby="comparison-closing-title">
            <div class="wp-welcome-section-inner">
                <h2 id="comparison-closing-title" class="wp-welcome-h1 wp-welcome-h1--closing">
                    {{ __('comparison.closing.title') }}
                </h2>
                <p class="wp-welcome-lead">{{ __('comparison.closing.body') }}</p>
                <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('comparison.closing.cta') }}</a>
            </div>
        </section>
    </main>

    <footer class="wp-welcome-footer">
        <div class="wp-welcome-footer-inner">
            @include('partials.wp-welcome-brand')
            <nav class="wp-welcome-footer-nav" aria-label="{{ __('legal.index_title') }}">
                @foreach (config('legal.documents', []) as $docMeta)
                    <a href="{{ route($docMeta['route']) }}" target="_blank" rel="noopener">{{ __($docMeta['label_key']) }}</a>
                @endforeach
                <a href="{{ route('contact.index') }}">{{ __('welcome.footer.contact') }}</a>
            </nav>
            <p>&copy; {{ date('Y') }} WinProx. {{ __('welcome.footer.rights') }}</p>
        </div>
    </footer>
</body>
</html>
