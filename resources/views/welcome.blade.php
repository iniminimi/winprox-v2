@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.meta_title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-welcome-shell">
    <header class="wp-welcome-header">
        <a href="{{ route('login') }}" class="wp-brand">WinProx</a>
        <div class="wp-welcome-header-actions">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
            <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
            <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.register') }}</a>
        </div>
    </header>

    <main class="wp-welcome">
        <section class="wp-welcome-hero">
            <p class="wp-welcome-kicker">{{ __('welcome.hero.kicker') }}</p>
            <h1 class="wp-welcome-title">{{ __('welcome.hero.title') }}</h1>
            <p class="wp-welcome-lead">{{ __('welcome.hero.lead') }}</p>
            <div class="wp-welcome-cta">
                <a href="{{ route('register') }}" class="btn btn--primary">{{ __('welcome.hero.cta_register') }}</a>
                <a href="{{ route('login') }}" class="btn btn--ghost">{{ __('welcome.hero.cta_login') }}</a>
            </div>
        </section>

        <section class="wp-welcome-section" aria-labelledby="welcome-problem-title">
            <h2 id="welcome-problem-title" class="wp-welcome-section-title">{{ __('welcome.problem.title') }}</h2>
            <div class="wp-welcome-cards">
                @foreach (__('welcome.problem.cards') as $card)
                    <article class="wp-welcome-card">
                        <h3>{{ $card['title'] ?? '' }}</h3>
                        <p class="wp-muted">{{ $card['body'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-solution" aria-labelledby="welcome-solution-title">
            <div class="wp-welcome-solution-text">
                <h2 id="welcome-solution-title" class="wp-welcome-section-title">{{ __('welcome.solution.title') }}</h2>
                <p class="wp-welcome-lead">{{ __('welcome.solution.lead') }}</p>
            </div>
            <div class="wp-welcome-qr-art" aria-hidden="true">
                <div class="wp-welcome-qr-grid"></div>
                <span class="wp-welcome-qr-label">{{ __('welcome.qr_illustration_label') }}</span>
            </div>
        </section>

        <section class="wp-welcome-section" aria-labelledby="welcome-features-title">
            <h2 id="welcome-features-title" class="wp-welcome-section-title">{{ __('welcome.features.title') }}</h2>
            <div class="wp-welcome-features">
                @foreach (__('welcome.features.items') as $feature)
                    <article class="wp-welcome-feature">
                        <span class="wp-welcome-feature-icon" aria-hidden="true">
                            <x-wp-icon :name="$feature['icon'] ?? 'issues'" class="wp-icon" />
                        </span>
                        <div>
                            <h3>{{ $feature['title'] ?? '' }}</h3>
                            <p class="wp-muted">{{ $feature['body'] ?? '' }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="wp-welcome-flow" aria-labelledby="welcome-flow-title">
            <h2 id="welcome-flow-title" class="wp-welcome-section-title">{{ __('welcome.flow.title') }}</h2>
            <ol class="wp-welcome-steps">
                @foreach (__('welcome.flow.steps') as $index => $step)
                    <li class="wp-welcome-step">
                        <span class="wp-welcome-step-num">{{ $index + 1 }}</span>
                        <div>
                            <h3>{{ $step['title'] ?? '' }}</h3>
                            <p class="wp-muted">{{ $step['body'] ?? '' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="wp-welcome-cta-band">
            <h2 class="wp-welcome-cta-band-title">{{ __('welcome.cta_band.title') }}</h2>
            <p class="wp-muted">{{ __('welcome.cta_band.body') }}</p>
            <div class="wp-welcome-cta">
                <a href="{{ route('register') }}" class="btn btn--primary">{{ __('welcome.cta_band.register') }}</a>
                <a href="{{ route('login') }}" class="btn btn--ghost">{{ __('welcome.cta_band.login') }}</a>
            </div>
        </section>
    </main>

    <footer class="wp-welcome-footer">
        <nav class="wp-welcome-footer-nav" aria-label="{{ __('legal.index_title') }}">
            @foreach (config('legal.documents', []) as $docKey => $docMeta)
                <a href="{{ route($docMeta['route']) }}" target="_blank" rel="noopener">{{ __($docMeta['label_key']) }}</a>
            @endforeach
            <a href="{{ route('contact.index') }}">{{ __('welcome.footer.contact') }}</a>
        </nav>
        <p class="wp-muted wp-text-sm">&copy; {{ date('Y') }} WinProx</p>
    </footer>
</body>
</html>
